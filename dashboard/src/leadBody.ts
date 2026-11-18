export const sampleLead = {
  phone: '+4915112345678',
  email: 'ada@example.com',
  first_name: 'Ada',
  last_name: 'Lovelace',
  country_code: 'DE',
  ip: '192.0.2.10',
  aff_sub: 'demo',
  traffic_source: 'facebook',
  age: '25',
}

export function sampleLeadJson(): string {
  return JSON.stringify(sampleLead, null, 2)
}
