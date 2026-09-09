/**
 * One copy of the database sitting on this computer.
 *
 * Taken by hand from Settings → Backup, or automatically by the updater before
 * it replaced any files — the two are the same kind of file in the same folder,
 * and the screen makes no distinction because neither does a restore.
 */
export type BackupRow = {
    /** The filename, which is also what identifies it in the download URL. */
    name: string;
    bytes: number;
    taken_at: string;
};
