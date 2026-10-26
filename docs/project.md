# Project

The **Project** custom post type groups related Issues and Invoices.

## Fields

- The standard WordPress title field stores the project name.
- The standard WordPress editor stores the project description.
- The standard WordPress featured image stores the project image.
- A CMB2 **Project contact information** group contains:
  - Product owner;
  - Company address;
  - Production website;
  - Contact email.
- A CMB2 **Product owner** field lets an administrator select one WordPress user for the Project.

The Project edit screen includes a custom **Create invoice** action.

## Product Owner access

LS_TM provides a **Product Owner** user role for people who need visibility into delivery.

- An administrator assigns a Product Owner through the Project edit screen.
- A Product Owner can view their assigned Project, its Issues, and its Invoices.
- A Product Owner cannot view Projects, Issues, or Invoices assigned to other Product Owners.
- Administrators retain access to all projects, issues, and invoices.
