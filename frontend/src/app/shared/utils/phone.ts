import { AsYouType, CountryCode, getCountries, getCountryCallingCode, isValidPhoneNumber, parsePhoneNumber } from 'libphonenumber-js/min';

export interface CountryOption {
  code: CountryCode;
  name: string;
  dial: string;
  flag: string;
}

const flag = (code: string) => String.fromCodePoint(...[...code].map((c) => 0x1f1a5 + c.charCodeAt(0)));

/** Lista de países (México primero, luego alfabético en español). */
export const COUNTRIES: CountryOption[] = (() => {
  const names = new Intl.DisplayNames(['es'], { type: 'region' });
  const all = getCountries().map((code) => ({
    code,
    name: names.of(code) ?? code,
    dial: `+${getCountryCallingCode(code)}`,
    flag: flag(code),
  }));
  all.sort((a, b) => a.name.localeCompare(b.name, 'es'));
  const mx = all.findIndex((c) => c.code === 'MX');
  return mx > 0 ? [all[mx], ...all.slice(0, mx), ...all.slice(mx + 1)] : all;
})();

export const formatAsYouType = (value: string, country: CountryCode) => new AsYouType(country).input(value);

export const isValidPhone = (value: string, country: CountryCode) => {
  try {
    return isValidPhoneNumber(value, country);
  } catch {
    return false;
  }
};

/** Formato E.164 (+523141234567) que exige el backend. */
export const toE164 = (value: string, country: CountryCode): string | null => {
  try {
    return parsePhoneNumber(value, country).number;
  } catch {
    return null;
  }
};

/** Separa un E.164 existente en país + número nacional (para editar). */
export const splitE164 = (e164: string | null): { country: CountryCode; national: string } => {
  if (!e164) return { country: 'MX', national: '' };
  try {
    const parsed = parsePhoneNumber(e164);
    return { country: parsed.country ?? 'MX', national: parsed.formatNational() };
  } catch {
    return { country: 'MX', national: e164 };
  }
};
