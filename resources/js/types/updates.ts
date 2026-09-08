/**
 * One release: a commit on the branch this copy follows.
 *
 * There is no version number to bump. CI publishes one commit per release, so
 * the commit already carries an identity, a date and a description — and none
 * of those can disagree with the code actually installed.
 */
export type Release = {
    sha: string;
    /** The seven characters somebody can read out over the phone. */
    short: string;
    committed_at: string;
    subject: string;
};

/**
 * What the app knows about waiting updates, carried on every screen.
 *
 * Always a remembered answer — nothing on the server fetches during a page
 * load. `stale` is the server saying the answer is old enough to be worth
 * asking again, which `UpdateNotice` does from the browser.
 */
export type UpdateAnnouncement = {
    /** False when this copy has no update source configured. */
    can_check: boolean;
    checked_at: string | null;
    stale: boolean;
    available: boolean;
    latest: Release | null;
    /** Release subjects between what is installed and what is waiting. */
    changes: string[];
};
