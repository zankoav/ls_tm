# REST API

LS_TM provides an authenticated REST API for Projects, Issues, and Invoices. It allows an AI agent or another authorized client to work with the task manager programmatically.

- The API supports creating, reading, updating, and deleting Projects, Issues, and Invoices.
- Every API request must be authenticated.
- Permissions follow the WordPress interface: administrators can access all data, while Product Owners can access only their assigned Project and its Issues and Invoices.
- The API exposes the fields defined in the requirements, including Issue type, status, priority, estimated hours, Figma prototype URL, Project, Product Owner, Invoice, and Invoice status.

See [REST API examples](examples/rest-api.md) for authenticated `curl` requests and example JSON payloads.
