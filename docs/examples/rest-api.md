# REST API examples

These examples use the local development site at `http://localhost:8001`.
Replace the base URL, user name, application password, and example IDs before using them elsewhere.

## Authentication

The LS_TM API is authenticated through WordPress. For a script, integration, or AI agent, create a WordPress **Application Password** for an administrator account, then use HTTP Basic authentication. Product Owners can read only the Projects, Issues, and Invoices assigned to them; only administrators can create, update, or delete records.

```bash
export LS_TM_URL="http://localhost:8001"
export WP_USER="your-wordpress-user"
export WP_APP_PASSWORD="your-application-password"
```

All endpoints start with:

```text
/wp-json/ls-tm/v1
```

For example, list Projects:

```bash
curl --user "$WP_USER:$WP_APP_PASSWORD" \
  "$LS_TM_URL/wp-json/ls-tm/v1/projects"
```

## Projects

Create a Project. `product_owner_id` is the WordPress user ID of the assigned Product Owner.

```bash
curl --request POST \
  --user "$WP_USER:$WP_APP_PASSWORD" \
  --header "Content-Type: application/json" \
  --data '{
    "title": "Example website",
    "content": "Internal project description.",
    "product_owner_id": 12,
    "company_address": "Minsk, Belarus",
    "production_website": "https://example.com",
    "contact_email": "owner@example.com"
  }' \
  "$LS_TM_URL/wp-json/ls-tm/v1/projects"
```

Get, update, or delete a Project:

```bash
curl --user "$WP_USER:$WP_APP_PASSWORD" \
  "$LS_TM_URL/wp-json/ls-tm/v1/projects/123"

curl --request PUT \
  --user "$WP_USER:$WP_APP_PASSWORD" \
  --header "Content-Type: application/json" \
  --data '{"contact_email":"new-owner@example.com"}' \
  "$LS_TM_URL/wp-json/ls-tm/v1/projects/123"

curl --request DELETE \
  --user "$WP_USER:$WP_APP_PASSWORD" \
  "$LS_TM_URL/wp-json/ls-tm/v1/projects/123"
```

## Issues

Create an Issue for a Project. Valid Issue types are `bug` and `feature`. Valid statuses are `open`, `in_progress`, `blocked`, `in_review`, `completed`, `archived`, and `deleted`. Valid priorities are `high`, `medium`, and `low`.

```bash
curl --request POST \
  --user "$WP_USER:$WP_APP_PASSWORD" \
  --header "Content-Type: application/json" \
  --data '{
    "title": "Add profile screen",
    "content": "The Product Owner can update their contact details.",
    "project_id": 123,
    "issue_type": "feature",
    "status": "open",
    "priority": "high",
    "estimated_hours": 4.5,
    "figma_url": "https://www.figma.com/file/example"
  }' \
  "$LS_TM_URL/wp-json/ls-tm/v1/issues"
```

Move an Issue into review, then mark it completed:

```bash
curl --request PUT \
  --user "$WP_USER:$WP_APP_PASSWORD" \
  --header "Content-Type: application/json" \
  --data '{"status":"in_review"}' \
  "$LS_TM_URL/wp-json/ls-tm/v1/issues/456"

curl --request PUT \
  --user "$WP_USER:$WP_APP_PASSWORD" \
  --header "Content-Type: application/json" \
  --data '{"status":"completed"}' \
  "$LS_TM_URL/wp-json/ls-tm/v1/issues/456"
```

## Invoices

An Invoice can include only completed Issues from its own Project. An Issue can belong to only one Invoice. `hourly_rate` and `total_price` are calculated by LS_TM and are not accepted as API input.

Create an Invoice with completed Issues:

```bash
curl --request POST \
  --user "$WP_USER:$WP_APP_PASSWORD" \
  --header "Content-Type: application/json" \
  --data '{
    "title": "October 2026 work",
    "project_id": 123,
    "issue_ids": [456, 457],
    "status": "created"
  }' \
  "$LS_TM_URL/wp-json/ls-tm/v1/invoices"
```

Set an Invoice to `sent` after sharing it, or to `paid` once payment arrives. When an Invoice becomes `paid`, LS_TM archives its Issues so they are not included in the next Invoice.

```bash
curl --request PUT \
  --user "$WP_USER:$WP_APP_PASSWORD" \
  --header "Content-Type: application/json" \
  --data '{"status":"sent"}' \
  "$LS_TM_URL/wp-json/ls-tm/v1/invoices/789"

curl --request PUT \
  --user "$WP_USER:$WP_APP_PASSWORD" \
  --header "Content-Type: application/json" \
  --data '{"status":"paid"}' \
  "$LS_TM_URL/wp-json/ls-tm/v1/invoices/789"
```

## Typical response

Successful requests return JSON. An Invoice response includes its saved Issue IDs, the fixed hourly rate in BYN, the calculated total, and the PDF URL after a PDF has been generated in WordPress.

```json
{
  "id": 789,
  "type": "ls_tm_invoice",
  "title": "October 2026 work",
  "project_id": 123,
  "issue_ids": [456, 457],
  "status": "created",
  "hourly_rate": 25,
  "total_price": 162.5,
  "pdf_url": null
}
```

## Errors

- `401` or `403`: authenticate with a WordPress user that has permission for the requested record.
- `400`: check that every Invoice Issue is completed and belongs to the Invoice Project.
- `409`: the Issue is already assigned to another Invoice, or the Invoice is already paid.
- `404`: the record does not exist or is not available to the current user.
