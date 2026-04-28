# Process: Batch Photography Workflow

## Objective
Reduce the time spent on listing photos by creating a "Model Photo Bank" that can be reused for future batches of the same hardware.

## Standard Shots
Every new model entry should have exactly 3 "Hero Shots":
1. **The Scale Shot**: The full pallet or a stack of units (proves volume).
2. **The Detail Shot**: A close-up of a single, cleaned unit (proves quality).
3. **The Proof Shot**: A clear photo of the BIOS or System Info screen (proves specs).

## Storage Convention
- Files should be stored in `assets/img/models/[model_name]/`.
- Filenames: `pallet.jpg`, `unit.jpg`, `bios.jpg`.

## Agent Instructions
- When building the "Model Template" interface, add an image uploader/previewer that looks for these specific filenames.
- Implement a "Copy to Clipboard" for image paths if the user is listing on external platforms.
