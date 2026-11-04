<?php

use App\Domain\Clients\Services\CompanyNameNormalizer;
use App\Domain\Clients\Services\VatNumber;

/*
| Duplicate keys of a client (M05 RB-03, §8; D-080, D-361): normalized company name and Italian VAT
| number. The duplicate check on the whole registry is in ClientsPagesTest.
*/

it('RB-03, D-361: "Adecco S.p.A.", "ADECCO SPA" and "Adecco" have the same normalized name', function () {
    expect(CompanyNameNormalizer::normalize('Adecco S.p.A.'))->toBe('ADECCO')
        ->and(CompanyNameNormalizer::normalize('ADECCO SPA'))->toBe('ADECCO')
        ->and(CompanyNameNormalizer::normalize('  adecco  '))->toBe('ADECCO');
});

it('D-361: accents, spaces, punctuation and the legal forms at the end are ignored', function (string $name, string $normalized) {
    expect(CompanyNameNormalizer::normalize($name))->toBe($normalized);
})->with([
    'srl' => ['Rossi Srl', 'ROSSI'],
    'srls' => ['Rossi S.r.l.s.', 'ROSSI'],
    'società benefit' => ['Rossi S.r.l. SB', 'ROSSI'],
    'sapa' => ['Rossi S.a.p.A.', 'ROSSI'],
    'sas' => ['Rossi & C. s.a.s.', 'ROSSIC'],
    'snc with accents' => ['Caffè Néro snc', 'CAFFENERO'],
    'ss' => ['Azienda agricola Bianchi S.S.', 'AZIENDAAGRICOLABIANCHI'],
    'scarl' => ['Consorzio Alfa S.c.a.r.l.', 'CONSORZIOALFA'],
    'soc coop' => ['Città Verde Soc. Coop.', 'CITTAVERDE'],
    'soc coop without spaces' => ['Città Verde Soc.Coop.', 'CITTAVERDE'],
    'onlus' => ['Fondazione Beta Onlus', 'FONDAZIONEBETA'],
    'two forms' => ['Gamma Coop Onlus', 'GAMMA'],
    'only in the middle' => ['Spa Termale Delta', 'SPATERMALEDELTA'],
    'inside a word' => ['Hugo Boss', 'HUGOBOSS'],
    'only a legal form' => ['S.p.A.', 'SPA'],
]);

it('M05 §8, D-361: an Italian VAT number has 11 digits and the official check digit', function () {
    expect(VatNumber::isValid('01234567897'))->toBeTrue()
        ->and(VatNumber::isValid('09876543217'))->toBeTrue()
        ->and(VatNumber::isValid('09876543210'))->toBeFalse()
        ->and(VatNumber::isValid('0123456789'))->toBeFalse()
        ->and(VatNumber::isValid('0123456789A'))->toBeFalse();
});

it('D-361: spaces and an "IT" prefix are dropped, an empty field is no VAT number', function () {
    expect(VatNumber::clean('IT 0123 4567 897'))->toBe('01234567897')
        ->and(VatNumber::clean('it01234567897'))->toBe('01234567897')
        ->and(VatNumber::clean('  '))->toBeNull()
        ->and(VatNumber::clean(null))->toBeNull();
});
