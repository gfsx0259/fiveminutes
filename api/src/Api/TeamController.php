<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Form\ExportForm;
use App\Api\Form\TeamAccessForm;
use App\Api\Form\TeamBudgetForm;
use App\Api\Form\TeamHandOverForm;
use App\Api\Form\TeamInviteForm;
use App\Api\Form\TeamOrderForm;
use App\Api\Form\TeamParentForm;
use App\Api\Form\TeamUnitForm;
use App\Context\Identity\CurrentUser;
use App\Context\Identity\NoAccountException;
use App\Context\Identity\NoSessionException;
use App\Context\Market\OrderMissingException;
use App\Context\Team\AccessDeniedException;
use App\Context\Team\MemberMissingException;
use App\Context\Team\NotTeamLeadException;
use App\Context\Team\Permission;
use App\Context\Team\Seat;
use App\Context\Team\TeamAction;
use App\Context\Team\TeamBudget;
use App\Context\Team\TeamDesk;
use App\Context\Team\TeamJournal;
use App\Context\Team\TeamOrders;
use App\Context\Team\TeamUnitMissingException;
use App\Context\Team\TeamView;
use App\Context\User\User;
use App\Context\User\UserRepository;
use App\Context\User\UserRole;
use DomainException;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\RequestProvider\RequestProviderInterface;
use Yiisoft\Router\CurrentRoute;

final readonly class TeamController
{
    public function __construct(
        private CurrentUser $currentUser,
        private UserRepository $users,
        private TeamDesk $team,
        private TeamBudget $budget,
        private TeamOrders $orders,
        private TeamJournal $journal,
        private TeamView $view,
        private DataResponseFactoryInterface $responses,
        private RequestProviderInterface $requests,
        private CurrentRoute $route,
    ) {}

    public function index(): ResponseInterface
    {
        return $this->respond(fn (Seat $seat): array => $this->view->roster($seat));
    }

    public function events(): ResponseInterface
    {
        return $this->respond(fn (Seat $seat): array => [
            'events' => $this->view->events($this->journal->latest($seat, $this->team->tree($seat))),
        ]);
    }

    public function invite(TeamInviteForm $form): ResponseInterface
    {
        return $this->respond(fn (Seat $seat): array => [
            'member' => $this->view->member(
                $this->team->invite($seat, $form->teamId(), $form->email(), $form->permissions()),
                $this->view->signUpUrl($seat),
            ),
        ], 201);
    }

    public function renew(): ResponseInterface
    {
        return $this->respond(fn (Seat $seat): array => [
            'member' => $this->view->member($this->team->renew($seat, $this->id()), $this->view->signUpUrl($seat)),
        ]);
    }

    public function update(TeamAccessForm $form): ResponseInterface
    {
        return $this->respond(fn (Seat $seat): array => [
            'member' => $this->view->member(
                $this->team->update($seat, $this->id(), $form->teamId(), $form->permissions()),
                $this->view->signUpUrl($seat),
            ),
        ]);
    }

    public function remove(): ResponseInterface
    {
        return $this->respond(function (Seat $seat): array {
            $this->team->remove($seat, $this->id());

            return ['status' => 'removed'];
        });
    }

    public function handOver(TeamHandOverForm $form): ResponseInterface
    {
        return $this->respond(function (Seat $seat) use ($form): array {
            $this->team->handOver($seat, $form->memberId(), $form->keepFormerOwner());

            return ['status' => 'handed_over'];
        });
    }

    public function createUnit(TeamUnitForm $form): ResponseInterface
    {
        return $this->respond(fn (Seat $seat): array => [
            'team' => ['id' => $this->team->createUnit($seat, $form->parentId(), $form->name())->getId()],
        ], 201);
    }

    public function renameUnit(TeamUnitForm $form): ResponseInterface
    {
        return $this->respond(fn (Seat $seat): array => [
            'team' => ['id' => $this->team->renameUnit($seat, $this->id(), $form->name())->getId()],
        ]);
    }

    public function moveUnit(TeamParentForm $form): ResponseInterface
    {
        return $this->respond(fn (Seat $seat): array => [
            'team' => ['id' => $this->team->moveUnit($seat, $this->id(), $form->parentId())->getId()],
        ]);
    }

    public function removeUnit(): ResponseInterface
    {
        return $this->respond(function (Seat $seat): array {
            $this->team->removeUnit($seat, $this->id());

            return ['status' => 'removed'];
        });
    }

    public function limitBudget(TeamBudgetForm $form): ResponseInterface
    {
        return $this->respond(function (Seat $seat) use ($form): array {
            $unit = $this->budget->limit($seat, $this->id(), $form->budgetCents(), $form->period());
            $this->budget->alert($seat->getAccount());

            return ['team' => ['id' => $unit->getId()]];
        });
    }

    public function budgetHistory(): ResponseInterface
    {
        return $this->respond(fn (Seat $seat): array => [
            'changes' => $this->view->budgetHistory($this->budget->history($seat, $this->id())),
        ]);
    }

    public function moveOrder(TeamOrderForm $form): ResponseInterface
    {
        return $this->respond(function (Seat $seat) use ($form): array {
            $this->orders->move($seat, $this->id(), $form->teamId());
            $this->budget->alert($seat->getAccount());

            return ['status' => 'moved'];
        });
    }

    public function export(ExportForm $form): ResponseInterface
    {
        return $this->respond(function (Seat $seat) use ($form): array {
            $seat->authorize(Permission::ReportsExport);
            if ($seat->getAccount()->getRole() === UserRole::Partner) {
                $this->journal->log($seat->getAccount(), $seat->email(), TeamAction::DataExported, $seat->unitId(), [
                    'kind' => $form->kind(),
                    'rows' => $form->rows(),
                ]);
            }

            return ['status' => 'allowed'];
        });
    }

    public function partnerTeam(): ResponseInterface
    {
        return $this->respondFor(fn (Seat $seat): array => $this->view->roster($seat));
    }

    public function removePartnerMember(): ResponseInterface
    {
        return $this->respondFor(function (Seat $seat): array {
            $this->team->remove($seat, (string) $this->route->getArgument('member'));

            return ['status' => 'removed'];
        });
    }

    /**
     * @param callable(Seat): array<string, mixed> $action
     */
    private function respond(callable $action, int $status = 200): ResponseInterface
    {
        try {
            $body = $action($this->currentUser->seat($this->requests->get()->getHeaderLine('Cookie')));
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }

        return $this->responses->createResponse($body, $status);
    }

    /**
     * @param callable(Seat): array<string, mixed> $action
     */
    private function respondFor(callable $action): ResponseInterface
    {
        try {
            $admin = $this->currentUser->requireActor($this->requests->get()->getHeaderLine('Cookie'));
            if ($admin->getRole() !== UserRole::Admin) {
                throw new NotTeamLeadException('Only an admin sees partner teams');
            }
            $partner = $this->users->findByPK($this->id());
            if (!$partner instanceof User || $partner->getRole() !== UserRole::Partner || $partner->getOrganizationId() !== $admin->getOrganizationId()) {
                throw new MemberMissingException();
            }
            $body = $action(new Seat($partner, null, $admin));
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }

        return $this->responses->createResponse($body);
    }

    private function id(): string
    {
        return (string) $this->route->getArgument('id');
    }

    private function failure(Throwable $exception): ResponseInterface
    {
        if ($exception instanceof NoSessionException) {
            return $this->responses->createResponse(['error' => 'No session'], 401);
        }
        if ($exception instanceof NoAccountException || $exception instanceof NotTeamLeadException || $exception instanceof AccessDeniedException) {
            return $this->responses->createResponse(['error' => $exception->getMessage()], 403);
        }
        if ($exception instanceof MemberMissingException || $exception instanceof TeamUnitMissingException || $exception instanceof OrderMissingException) {
            return $this->responses->createResponse(['error' => $exception->getMessage()], 404);
        }
        if ($exception instanceof InvalidArgumentException) {
            return $this->responses->createResponse(['error' => $exception->getMessage()], 400);
        }
        if ($exception instanceof DomainException) {
            return $this->responses->createResponse(['error' => $exception->getMessage()], 409);
        }

        throw $exception;
    }
}
