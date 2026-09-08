import { useEffect } from 'react';

import { cn } from '@/lib/utils';

/** The three faces the mark is drawn from, for asking the browser up front. */
const FACES = [
    '1em "UniQAIDAR BiLaL"',
    '1em "Shrikhand"',
    'bold 1em "Poppins"',
];

export type LogoProps = {
    /**
     * Sizes the whole mark. Everything inside is in `em`, so one font size on
     * the way in scales the initial, the word and the strapline together.
     */
    className?: string;
    /** The line under the word. Drop it where the mark sits small. */
    tagline?: boolean;
};

/**
 * The Yasamin mark, set rather than drawn.
 *
 * Three faces, each doing the job it was picked for: the ornate `Y` from
 * UniQAIDAR, `ASAMIN` in Shrikhand, and the strapline in Poppins Bold. Real
 * type rather than an SVG trace stays sharp at any size and prints at whatever
 * the printer can do.
 *
 * **Every number here is derived from the fonts' own metrics**, so the pieces
 * sit together the way the artwork does rather than the way three guesses do:
 *
 * - `1em` on the way in IS the size of `ASAMIN`; the initial is 1.8× it.
 * - The strapline is `0.28em` with `0.02em` of tracking, which is what makes
 *   its line come out the same width as the word above it (Poppins advances
 *   for the string total 18.8em; the mark totals 5.46em).
 * - `-1.5em` lifts the strapline back under the word: `leading-none` leaves
 *   0.49em of the initial's line box below the baseline, and that margin takes
 *   it back out. It reads large because a child's `em` is its OWN font size —
 *   1.5 × 0.28em is 0.42em of the mark. **This is the one value to nudge** if
 *   the gap looks wrong.
 *
 * The word is pulled back under the initial's swash, which is how the original
 * overlaps; both glyphs sit on the baseline, so aligning baselines aligns them.
 *
 * It takes the colour around it rather than carrying one, so the masthead can
 * press it in the brand blue and the invoice's footer stamp in a lighter ink
 * without either fighting the other.
 */
export function Logo({ className, tagline = true }: LogoProps) {
    useEffect(() => {
        // A face is only fetched when something visible needs it, and the mark
        // on a printed invoice is `display: none` until the print stylesheet
        // applies — too late for the sheet coming out of the printer. Asking
        // for them here means they are in hand before anyone hits Print.
        FACES.forEach((face) => void document.fonts?.load(face));
    }, []);

    return (
        <span className={cn('inline-flex flex-col items-center', className)}>
            <span className="flex items-baseline leading-none whitespace-nowrap">
                <span className="font-logo-initial text-[1.8em]">Y</span>
                <span className="-ml-[0.16em] font-logo-word text-[1em]">
                    ASAMIN
                </span>
            </span>

            {tagline && (
                <span className="-mt-[1.5em] font-logo-tagline text-[0.28em] leading-none font-bold tracking-[0.02em] whitespace-nowrap">
                    For Photography and CCTV Solutions
                </span>
            )}
        </span>
    );
}
