# LS_TM

LS_TM is a WordPress plugin for a personal, self-hosted task manager. It is designed for a single person's workflow: simple to use, easy to customize, private, and fully under the owner's control.

Unlike enterprise task-management platforms such as Jira, LS_TM deliberately avoids unnecessary integrations and complexity. The goal is a lightweight task manager that can evolve with the user's own workflow.

The plugin will use CMB2 to manage custom fields for tasks and related WordPress content.

## Documentation

```text
docs/
├── data-model.md       Custom post types and their relationships
├── project.md          Project fields and Product Owner access
├── issue.md            Issue fields and Kanban workflow
├── invoice.md          Invoice workflow, PDF generation, and history
├── rest-api.md         Authenticated API and permissions
└── examples/
    └── rest-api.md     Practical cURL examples and request payloads
```

- [Data model](docs/data-model.md)
- [Projects](docs/project.md)
- [Issues](docs/issue.md)
- [Invoices](docs/invoice.md)
- [REST API](docs/rest-api.md)
- [REST API examples](docs/examples/rest-api.md)
