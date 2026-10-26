# Issue

The **Issue** custom post type is the central work item in LS_TM.

## Standard fields

- The standard WordPress title field stores the issue title.
- The standard WordPress editor stores the issue description.
- A CMB2 **Project** field stores the selected Project's WordPress ID.
- An optional **Figma prototype URL** links to a Figma board or prototype for the proposed solution.
- An **Estimated hours** field stores the expected number of hours needed to resolve the Issue.

## Type

Each Issue has one of these types:

- **Bug** — a problem that needs to be fixed.
- **Feature** — new functionality or an improvement to existing functionality.

Research or investigation types are outside the first version and can be added later if useful.

## Status

Each Issue has a Kanban status:

- **Open** — work has not been started.
- **In progress** — work is underway.
- **Blocked** — work cannot continue until a blocker is resolved.
- **In review** — work is ready for review.
- **Completed** — work is finished.
- **Archived** — work has been invoiced and paid; it remains in history but is excluded from future invoices.
- **Deleted** — work is no longer needed and leaves the workflow without being completed.

## Priority

Each Issue has one priority value:

- **High**;
- **Medium**;
- **Low**.
