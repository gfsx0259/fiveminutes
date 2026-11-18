<?php

declare(strict_types=1);

namespace App\Context\Lead;

use App\Value\Criterion;
use InvalidArgumentException;

final readonly class LeadSubmission
{
    private string $phone;
    private string $email;
    private string $country;
    private string $trafficSource;
    private string $age;
    private string $gender;
    private string $ip;
    private string $firstName;
    private string $lastName;
    private string $affSub;

    /**
     * @param array<mixed, mixed> $body
     */
    public function __construct(array $body)
    {
        $errors = [];
        $phone = $this->text($body, 'phone');
        if (preg_match('/^\+[1-9]\d{7,14}$/', $phone) !== 1) {
            $errors['phone'] = 'Phone must be E.164, like +4915112345678';
        }

        $email = $this->text($body, 'email');
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Email is invalid';
        }

        $country = strtoupper($this->text($body, 'country_code'));
        if (preg_match('/^[A-Z]{2}$/', $country) !== 1) {
            $errors['country_code'] = 'Country must be an ISO code, like DE';
        }

        $ip = $this->text($body, 'ip');
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            $errors['ip'] = 'IP is invalid';
        }

        $firstName = $this->limited($body, 'first_name', $errors);
        $lastName = $this->limited($body, 'last_name', $errors);
        $affSub = $this->limited($body, 'aff_sub', $errors);
        $trafficSource = $this->criterion($body, 'traffic_source', 'Traffic source', $errors);
        $age = $this->criterion($body, 'age', 'Age', $errors);
        $gender = $this->optionalCriterion($body, 'gender', 'Gender', $errors);

        if ($errors !== []) {
            throw new LeadRejectedException($errors);
        }

        $this->phone = $phone;
        $this->email = strtolower($email);
        $this->country = $country;
        $this->trafficSource = $trafficSource;
        $this->age = $age;
        $this->gender = $gender;
        $this->ip = $ip;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->affSub = $affSub;
    }

    public function phone(): string
    {
        return $this->phone;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function country(): string
    {
        return $this->country;
    }

    public function trafficSource(): string
    {
        return $this->trafficSource;
    }

    public function age(): string
    {
        return $this->age;
    }

    public function gender(): string
    {
        return $this->gender;
    }

    public function ip(): string
    {
        return $this->ip;
    }

    public function firstName(): string
    {
        return $this->firstName;
    }

    public function lastName(): string
    {
        return $this->lastName;
    }

    public function affSub(): string
    {
        return $this->affSub;
    }

    /**
     * @param array<mixed, mixed> $body
     */
    private function text(array $body, string $key): string
    {
        $value = $body[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    /**
     * @param array<mixed, mixed> $body
     * @param array<string, string> $errors
     */
    private function limited(array $body, string $key, array &$errors): string
    {
        $value = $this->text($body, $key);
        if (strlen($value) > 120) {
            $errors[$key] = 'Must be 120 characters or fewer';
        }

        return $value;
    }

    /**
     * @param array<mixed, mixed> $body
     * @param array<string, string> $errors
     */
    private function criterion(array $body, string $key, string $label, array &$errors): string
    {
        try {
            return (new Criterion($this->text($body, $key), $label))->toString();
        } catch (InvalidArgumentException $exception) {
            $errors[$key] = $exception->getMessage();

            return '';
        }
    }

    /**
     * @param array<mixed, mixed> $body
     * @param array<string, string> $errors
     */
    private function optionalCriterion(array $body, string $key, string $label, array &$errors): string
    {
        $value = $this->text($body, $key);
        if ($value === '') {
            return '';
        }

        return $this->criterion($body, $key, $label, $errors);
    }
}
