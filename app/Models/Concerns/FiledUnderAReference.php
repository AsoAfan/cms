<?php

namespace App\Models\Concerns;

/**
 * A document filed under a reference the user owns.
 *
 * The reference is prefilled by the drawer and renamed in place on the
 * document's own page, so the sequence has to follow whatever the business
 * actually files under rather than impose a shape of its own. Shared by
 * `Purchase` and `Sale`, which differ only in what an untouched sequence is
 * called.
 */
trait FiledUnderAReference
{
    /**
     * What an automatically assigned reference starts with, e.g. 'SAL-'.
     */
    abstract protected static function referencePrefix(): string;

    /**
     * The next filing reference: the greatest one on record, plus one.
     *
     * Greatest is by length first and then by the characters themselves, which
     * puts SAL-00009 below SAL-00010 and 999 below 1000 without asking the
     * database to parse anything out of the column.
     *
     * **Whatever shape that reference is written in is the shape the next one
     * takes** — file an invoice as INV/2026/014 and the next is INV/2026/015,
     * keeping the width it was padded to. A business's own numbering is the one
     * its paperwork has to agree with, and counting off `max('id')` instead
     * would hand back a number from behind whatever was last filed.
     *
     * An empty table, or a greatest reference with no number in it at all,
     * starts the built-in sequence. Anything already taken is stepped over:
     * collations differ on case and a suggestion that cannot be saved is worse
     * than none.
     */
    public static function nextNumber(): string
    {
        $greatest = static::query()
            ->orderByRaw('LENGTH(number) DESC')
            ->orderBy('number', 'desc')
            ->value('number');

        [$prefix, $sequence, $width] = self::readReference($greatest)
            ?? [static::referencePrefix(), 0, 5];

        do {
            $number = $prefix.str_pad((string) ++$sequence, $width, '0', STR_PAD_LEFT);
        } while (static::query()->where('number', $number)->exists());

        return $number;
    }

    /**
     * A reference split into the part that stays put and the number that counts.
     *
     * @return array{string, int, int}|null Everything before the trailing
     *                                      number, the number itself, and how
     *                                      many digits it was written in — null
     *                                      when the reference ends in no number.
     */
    private static function readReference(?string $reference): ?array
    {
        if ($reference === null || preg_match('/^(.*?)(\d+)$/', $reference, $matches) !== 1) {
            return null;
        }

        return [$matches[1], (int) $matches[2], strlen($matches[2])];
    }
}
