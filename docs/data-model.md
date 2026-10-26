# Data model

LS_TM uses three WordPress custom post types:

- **Project** groups related work.
- **Issue** represents a task that needs to be done.
- **Invoice** records completed work prepared for payment.

## Relationships

```text
Project 1 ── * Issue
Project 1 ── * Invoice
Invoice 1 ── * Issue
```

- Each Issue belongs to one Project.
- Each Project can have many Issues and many Invoices.
- Each Invoice belongs to one Project.
- An Invoice can include many Issues.
- An Issue can belong to no more than one Invoice.
- An Invoice can include only completed Issues from its Project that are not already linked to another Invoice.

If an Issue is removed from an unpaid Invoice, its Invoice field is cleared and it becomes available for a later Invoice.
