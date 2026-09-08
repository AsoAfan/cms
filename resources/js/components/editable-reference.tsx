import { useForm } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useRef, useState } from 'react';

import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

/** The heading the reference is set in, whether it is being edited or not. */
const HEADING = 'font-mono text-2xl font-semibold tracking-tight';

export type EditableReferenceProps = {
    /** What the document is filed under now. */
    value: string;
    /** Where to PATCH a new one — `rename.url(id)`. */
    url: string;
    /** What is being renamed, for the screen reader: "sale", "invoice". */
    noun: string;
};

/**
 * The reference at the top of a document, edited where it is written.
 *
 * Click the heading and it becomes the field; Enter or clicking away files it,
 * Escape leaves it as it was. There is no drawer to open and no page to go to,
 * because correcting a reference is a one-field change on a document that may
 * already have moved stock — reopening the whole invoice to do it would put the
 * stock back and take it out again, and on an invoice whose goods have been
 * sold on that fails outright.
 */
export function EditableReference({
    value,
    url,
    noun,
}: EditableReferenceProps) {
    const [editing, setEditing] = useState(false);
    const form = useForm({ number: value });

    // Escape and a save in flight both blur the field, and neither wants the
    // blur to start a second save behind it.
    const settled = useRef(false);

    function open() {
        settled.current = false;
        form.clearErrors();
        form.setData('number', value);
        setEditing(true);
    }

    function close() {
        settled.current = true;
        form.clearErrors();
        setEditing(false);
    }

    function save() {
        if (settled.current || form.processing) {
            return;
        }

        // Typed back to what it was, or never touched: nothing to write.
        if (form.data.number.trim() === value) {
            close();

            return;
        }

        settled.current = true;

        form.patch(url, {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
            // A reference somebody else is already filed under comes back as an
            // error, and the field stays open on what was typed so the person
            // who typed it can correct it.
            onError: () => {
                settled.current = false;
            },
        });
    }

    return (
        <div className="grid gap-1">
            <h1 className={HEADING}>
                {editing ? (
                    <Input
                        autoFocus
                        aria-label={`Reference for this ${noun}`}
                        aria-invalid={form.errors.number ? true : undefined}
                        className={cn(
                            HEADING,
                            'h-auto w-56 py-0.5 md:text-2xl',
                        )}
                        value={form.data.number}
                        disabled={form.processing}
                        onChange={(event) =>
                            form.setData('number', event.target.value)
                        }
                        onBlur={save}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                event.preventDefault();
                                save();
                            }

                            if (event.key === 'Escape') {
                                event.preventDefault();
                                close();
                            }
                        }}
                    />
                ) : (
                    <button
                        type="button"
                        onClick={open}
                        aria-label={`Rename this ${noun}, filed under ${value}`}
                        className={cn(
                            HEADING,
                            'group flex items-center gap-2 rounded-md text-left transition-colors hover:text-primary focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none',
                        )}
                    >
                        {value}
                        <Pencil
                            aria-hidden
                            className="size-4 opacity-0 transition-opacity group-hover:opacity-60 group-focus-visible:opacity-60"
                        />
                    </button>
                )}
            </h1>

            {editing &&
                (form.errors.number ? (
                    <p className="text-sm text-destructive">
                        {form.errors.number}
                    </p>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        Enter to file it, Escape to leave it.
                    </p>
                ))}
        </div>
    );
}
