<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * The filing reference a purchase or a sale is kept under.
 *
 * Two screens set it and one rule has to hold for both: the drawer, where it is
 * one field among many and may be left blank to keep whatever the document is
 * already filed under, and the reference on the document's own page, where it
 * is the whole request and blank means nothing.
 *
 * The reference is the user's, not the system's. `nextNumber()` only suggests
 * one, so the rules here are the only thing standing between a business's own
 * numbering and two documents nobody can tell apart.
 */
trait FilesUnderAReference
{
    /**
     * @param  string  $table  The table the reference must be unique in.
     * @param  Model|null  $document  The document being renamed, which is allowed to keep its own reference.
     * @return list<mixed>
     */
    protected function referenceRules(string $table, ?Model $document, bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'max:50',
            Rule::unique($table, 'number')->ignore($document),
        ];
    }

    /**
     * @param  string  $noun  What the other document is called on this screen — 'sale', 'invoice'.
     * @return array<string, string>
     */
    protected function referenceMessages(string $noun): array
    {
        return [
            'number.required' => 'A reference cannot be empty.',
            'number.unique' => "Another {$noun} is already filed under that reference.",
        ];
    }

    /**
     * Trim the reference before anything looks at it, so a stray space cannot
     * slip a second document past the unique rule reading identically to the
     * first. Call it from `prepareForValidation()`.
     */
    protected function trimReference(): void
    {
        if ($this->has('number')) {
            $this->merge(['number' => $this->string('number')->trim()->toString()]);
        }
    }

    /**
     * The reference as it should be stored, or null when the form left it blank
     * and the document keeps the one it has.
     */
    public function reference(): ?string
    {
        return $this->filled('number') ? $this->string('number')->toString() : null;
    }
}
