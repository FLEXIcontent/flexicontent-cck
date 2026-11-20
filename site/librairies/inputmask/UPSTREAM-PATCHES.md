# Local patches to the standalone Inputmask distribution

The shipped `inputmask.min.js` identifies itself as Inputmask 5.0.10. Its readable webpack distribution retains the upstream MIT license.

`EventRuler.on` queues focus/click handling to preserve Chrome's initial selection. A keyboard or paste event can arrive before those callbacks. With the currency alias, clicking an empty field and immediately typing `249.95` then produces `$ 0.95`, because the first digits enter the fractional slots before the caret is positioned.

The local patch drains the pending focus callbacks before keyboard/paste processing. Normal focus handling remains deferred; drained callbacks run once, and callbacks belonging to a removed or replaced mask are ignored. It does not change mask values, numeric formatting, or validation rules.

Keep or re-evaluate this patch when updating the distribution. `tests/native-input-masks.cjs` covers immediate typing and paste; the installed Joomla lab also checks persisted currency values.

A second numeric deletion defect appears when a populated/reopened currency
field is selected completely, cleared and retyped while focused. Removing valid
positions retained cached fractional tests, so `249.95` could become `.95`.
`handleRemove` now resets the numeric mask tests and positions for a complete
selection. Partial deletion follows the existing path, and the normal write,
input and change events still run. Tests cover populated dollar/euro fields,
Delete/Backspace, zero/negative amounts, partial deletion and event delivery.

FLEXIcontent's currency preset keeps fractional digits optional during entry,
pads to two decimals on blur, and preserves an optional blank value.
