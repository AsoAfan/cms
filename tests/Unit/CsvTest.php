<?php

use App\Support\Csv;
use App\Support\Money;

/**
 * The encoded body, without the byte-order mark, as a list of lines.
 *
 * Only the final terminator is dropped — trimming every trailing newline would
 * quietly swallow a last field that is legitimately empty.
 *
 * @return list<string>
 */
function csvLines(string $encoded): array
{
    $body = (string) preg_replace('/^\x{FEFF}/u', '', $encoded);

    return explode("\r\n", (string) preg_replace('/\r\n$/', '', $body));
}

it('writes a heading row and one row per record', function () {
    $csv = Csv::encode(['Category', 'Total'], [
        ['Rent', Money::fromDecimal('150.00')],
        ['Transport', Money::fromDecimal('40.00')],
    ]);

    expect(csvLines($csv))->toBe([
        'Category,Total',
        'Rent,150.00',
        'Transport,40.00',
    ]);
});

it('writes money as a plain decimal a spreadsheet can add up', function () {
    // No symbol and no thousands separator: "$1,234.56" arrives as text and
    // will not sum, which defeats the point of exporting at all.
    $csv = Csv::encode(['Amount'], [[Money::fromDecimal('1234.56')], [Money::fromDecimal('-99.05')]]);

    expect(csvLines($csv))->toBe(['Amount', '1234.56', '-99.05']);
});

it('announces UTF-8 so a spreadsheet does not mangle accents', function () {
    expect(Csv::encode(['Name'], [['Café']]))->toStartWith("\u{FEFF}");
});

it('quotes a field carrying a comma, a quote or a newline', function (string $value, string $expected) {
    expect(csvLines(Csv::encode(['Name'], [[$value]]))[1])->toBe($expected);
})->with([
    'comma' => ['Curtains, blackout', '"Curtains, blackout"'],
    'quote' => ['The "good" one', '"The ""good"" one"'],
    'newline' => ["Two\nlines", "\"Two\nlines\""],
    'leading space' => [' padded', '" padded"'],
]);

it('stops a spreadsheet from running text a user typed as a formula', function (string $value, string $expected) {
    expect(csvLines(Csv::encode(['Name'], [[$value]]))[1])->toBe($expected);
})->with([
    // A supplier named after a formula is a real attack, not a hypothetical.
    'equals' => ['=1+1', "'=1+1"],
    'at sign' => ['@SUM(A1)', "'@SUM(A1)"],
    'plus' => ['+cmd', "'+cmd"],
    'a hyphenated name is left alone' => ['Ann-Marie', 'Ann-Marie'],
]);

it('leaves a negative number negative', function () {
    // The formula guard must not reach numbers: a "-" here is a minus sign.
    expect(csvLines(Csv::encode(['Amount'], [['-1234.56']]))[1])->toBe('-1234.56');
});

it('writes an absent value as an empty field, not the word null', function () {
    expect(csvLines(Csv::encode(['Last sold'], [[null]]))[1])->toBe('');
});

it('writes a flag as a word rather than a bare 1', function () {
    expect(csvLines(Csv::encode(['Dead'], [[true], [false]])))
        ->toBe(['Dead', 'yes', 'no']);
});

it('ends every line the way the CSV spec asks for', function () {
    expect(Csv::encode(['A'], [['b']]))->toEndWith("A\r\nb\r\n");
});

it('writes just the heading when there is nothing to report', function () {
    expect(csvLines(Csv::encode(['Category', 'Total'], [])))->toBe(['Category,Total']);
});
