# Invoice

The **Invoice** custom post type records completed work prepared for payment.

## Invoice data

Each Invoice stores:

- Its Project and included completed Issues;
- The estimated hours for each included Issue;
- The global hourly rate used in Belarusian rubles (BYN) and the calculated total price;
- A PDF invoice file.

These values form an invoice snapshot and remain unchanged in the Invoice history. The hourly rate and total price are calculated fields and are read-only on the Invoice screen.

## Status

- **Created** — the Invoice is being prepared.
- **Sent** — the Invoice has been sent to the Product Owner.
- **Paid** — payment has been received.

When an Invoice becomes **Paid**, its linked Issues automatically move to **Archived**.

## PDF and delivery

The Invoice edit screen includes a **Generate PDF** button. An administrator can generate the PDF, download it, print it, or send it by email to the configured administrator address and the Project's Product Owner.

The generated PDF is saved in the WordPress Media Library and linked to the Invoice. The Project shows an invoice history, and the assigned Product Owner can view and download its invoices.
