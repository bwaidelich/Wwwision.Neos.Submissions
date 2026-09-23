# Form submissions for Neos

[Neos](https://neos.io) package that stores submissions of [Neos.Fusion.Form](https://github.com/neos/fusion-form) forms and provides a backend module to browse, search and export them

## How it works

* Submissions are grouped into **presets** that are declared in `Settings.yaml` – for example one preset per form type ("Contact", "Event registration", ...)
* Every preset stores its submissions in a **dedicated database table** that is created and migrated via a CLI command
* The package ships a **Fusion.Form action** (`Wwwision.Neos.Submissions:StoreSubmission`) that persists the submitted form data as JSON
* Each submission gets a **label** (e.g. "Doe, John (john@example.com)") and a **form label** (e.g. the title of the document containing the form) that are generated via configurable Eel expressions
* The **backend module** lists submissions per preset, allows to filter them by form and search term, shows the details of a single submission, exports the current selection as CSV file and allows to protect and delete submissions
* **CLI commands** allow to set up the storage, export submissions and re-generate labels

# Usage

1. Install the package via composer:

```shell
composer require wwwision/neos-submissions
```

2. Grant access to the backend module to the corresponding roles via `Policy.yaml`:

```yaml
roles:
  'Neos.Neos:Administrator':
    privileges:
      - privilegeTarget: 'Wwwision.Neos.Submissions.Module:SubmissionsModule'
        permission: GRANT
```

3. Declare a first preset via `Settings.yaml`:

```yaml
Wwwision:
  Neos:
    Submissions:
      presets:
        'contact':
          title: 'Contact forms'
          submissionLabel:
            options:
              eelExpression: '${data.familyName + ", " + data.givenName + " (" + data.emailAddress + ")"}'
```

4. Create the database table(s):

```shell
./flow submissions:setup
```

> [!NOTE]
> The command is safe to run repeatedly (e.g. as part of every deployment): it only creates missing tables, columns and indexes

5. Add the `StoreSubmission` action to your Fusion form:

```neosfusion
prototype(Some.Package:ContactForm) < prototype(Neos.Fusion.Form:Runtime.RuntimeForm) {
  process {
    content = afx`
      <Neos.Fusion.Form:FieldContainer field.name="givenName" label="Given name">
        <Neos.Fusion.Form:Input />
      </Neos.Fusion.Form:FieldContainer>
      <Neos.Fusion.Form:FieldContainer field.name="familyName" label="Family name">
        <Neos.Fusion.Form:Input />
      </Neos.Fusion.Form:FieldContainer>
      <Neos.Fusion.Form:FieldContainer field.name="emailAddress" label="Email">
        <Neos.Fusion.Form:Input attributes.type="email" />
      </Neos.Fusion.Form:FieldContainer>
    `
    schema {
      givenName = ${Form.Schema.string().isRequired()}
      familyName = ${Form.Schema.string().isRequired()}
      emailAddress = ${Form.Schema.string().isRequired().validator('EmailAddress')}
    }
  }

  action {
    storeSubmission {
      type = 'Wwwision.Neos.Submissions:StoreSubmission'
      options {
        preset = 'contact'
        formId = ${node.identifier}
        data = ${data}
      }
    }
    message {
      type = 'Neos.Fusion.Form.Runtime:Message'
      options.message = 'Thank you for your message!'
    }
  }
}
```

6. Navigate to the new backend module

Log in as Neos administrator and navigate to the new "Submissions" module underneath the "Administration" main module, or head straight to `/neos/administration/submissions`

# The StoreSubmission action

The action stores the submitted data and supports the following options:

* `preset` – id of the [preset](#declaring-presets) to store the submission in (required)
* `formId` – string identifying the form the submission belongs to (required). Using the identifier of the node containing the form allows the default [form label](#form-labels) expression to resolve the form's title. Any other string of up to 255 characters works as well, though
* `data` – the submitted form data, usually `${data}` (required)

The action can be combined with any other Fusion.Form action (e.g. `Neos.Fusion.Form.Runtime:Email` to send a notification, `Neos.Fusion.Form.Runtime:Message` to render a confirmation, ...).

> [!NOTE]
> The data is stored as JSON, so nested values (e.g. from `Neos.Fusion.Form:FieldContainer` with a `field.name` like `contact[emailAddress]`) are preserved and rendered as nested lists in the backend module and as `contact.emailAddress` columns in the CSV export

# Declaring presets

Presets are declared underneath the `Wwwision.Neos.Submissions.presets` setting:

```yaml
Wwwision:
  Neos:
    Submissions:
      presets:
        'contact':
          title: 'Contact forms'
          submissionLabel:
            options:
              eelExpression: '${data.familyName + ", " + data.givenName + " (" + data.emailAddress + ")"}'
          formLabel:
            options:
              eelExpression: '${q(site).find("#" + formId).closest("[instanceof Neos.Neos:Document]").get(0).label}'
        'eventRegistration':
          title: 'Event registrations'
          submissionLabel:
            options:
              eelExpression: '${data.familyName + ", " + data.givenName}'
          formLabel:
            options:
              eelExpression: '${q(site).find("#" + formId).parent().get(0).label}'
          options:
            tableName: 'some_custom_table_name'
```

Every preset supports the following settings:

* `title` – label displayed in the backend module (defaults to the preset id)
* `submissionLabel` – configuration of the [submission label](#submission-labels) generator
* `formLabel` – configuration of the [form label](#form-labels) generator
* `options.tableName` – name of the database table (defaults to `wwwision_neos_submissions_<presetId>`)

A preset can be disabled by setting it to `~` (null).

## Submission labels

The submission label is displayed in the list and detail views of the backend module and is exported as `label` column in the CSV file.
By default, it is generated from an Eel expression that has access to the following context variables:

* `submissionId` – the unique id of the submission
* `data` – the submitted form data (as array)

The default expression is `${submissionId}`.

## Form labels

The form label is displayed in the form filter of the backend module and in the detail view of a submission.
By default, it is generated from an Eel expression that has access to the following context variables:

* `formId` – the id that was passed to the `StoreSubmission` action
* `preset` – the corresponding `Preset` instance
* `site` – the current site node (in the `live` workspace)

The default expression is `${q(site).find("#" + formId).get(0).label}`, i.e. it assumes the `formId` to be the identifier of a node.

> [!NOTE]
> Labels must never prevent a submission from being stored: if an expression fails or yields an empty result (e.g. because the corresponding node has been deleted), the label falls back to the submission or form id respectively.
> Labels are generated when a submission is stored, use the [regeneratelabels command](#cli-commands) to update the labels of existing submissions after adjusting the expressions

## Custom label generators

For cases that can't be expressed via Eel, custom label generators can be provided by implementing the `SubmissionLabelGeneratorFactory` and/or `FormLabelGeneratorFactory` interfaces:

```php
<?php

declare(strict_types=1);

namespace Some\Package\Submissions;

use Wwwision\Neos\Submissions\LabelGenerator\SubmissionLabelGenerator\SubmissionLabelGenerator;
use Wwwision\Neos\Submissions\LabelGenerator\SubmissionLabelGenerator\SubmissionLabelGeneratorFactory;
use Wwwision\Neos\Submissions\Model\Submission\SubmissionData;
use Wwwision\Neos\Submissions\Model\Submission\SubmissionId;
use Wwwision\Neos\Submissions\Model\Submission\SubmissionLabel;

final readonly class OrderNumberLabelGeneratorFactory implements SubmissionLabelGeneratorFactory
{
    public function create(array $options): SubmissionLabelGenerator
    {
        return new class ($options['prefix'] ?? 'ORDER-') implements SubmissionLabelGenerator {
            public function __construct(private readonly string $prefix) {}

            public function generate(SubmissionId $submissionId, SubmissionData $data): SubmissionLabel
            {
                return SubmissionLabel::fromString($this->prefix . substr($submissionId->value, 0, 8));
            }
        };
    }
}
```

The factory is referenced via the `factory` setting of the corresponding preset; its `options` are passed to the `create()` method:

```yaml
Wwwision:
  Neos:
    Submissions:
      presets:
        'orders':
          submissionLabel:
            factory: 'Some\Package\Submissions\OrderNumberLabelGeneratorFactory'
            options:
              prefix: 'ORD-'
```

# Backend module

The "Submissions" module underneath the "Administration" main module provides:

* A **preset switch** (only shown if more than one preset is declared)
* A **form filter** to narrow down the list to submissions of a single form (only enabled if submissions for more than one form exist)
* A **search** that matches the submission label as well as the raw submitted data
* A paginated **list** of submissions with label and creation date
* A **detail view** rendering the submitted data (including nested values, dates and booleans)
* A **CSV download** of all submissions matching the current filter
* **Protection** of single submissions (lock icon in the list and detail view). Protected submissions are highlighted in the list and can't be deleted
* **Deletion** of a single (unprotected) submission in the detail view and of all unprotected submissions matching the current filter in the list view (after confirmation)

> [!NOTE]
> Submissions are never removed from the database by the module. "Deleting" a submission sets its `archivedAt` timestamp instead – archived submissions are excluded from the module, the CSV export and the CLI commands

The CSV export contains the fixed columns `id`, `formId`, `presetId`, `label`, `createdAt`, `archivedAt` and `protected` followed by one column per (flattened) form field. Submitted form fields that happen to have the same name as a fixed column are exported with a `data.` prefix.

> [!NOTE]
> Submitted data is user input. The backend module escapes all values and the CSV export neutralizes cells that spreadsheet applications would otherwise interpret as formulas

# CLI commands

```shell
# create/migrate the database tables of all presets (safe to run repeatedly)
./flow submissions:setup

# export submissions of a preset to a CSV file, optionally filtered by form and/or search term
./flow submissions:export --preset contact --output-file /tmp/contact.csv
./flow submissions:export --preset contact --output-file /tmp/contact.csv --form-id <node-identifier> --search-term "Doe"

# re-generate submission and form labels of all (or only one form's) submissions of a preset
./flow submissions:regeneratelabels --preset contact
./flow submissions:regeneratelabels --preset contact --form <node-identifier>
```

# Storing submissions programmatically

The `FormSubmissionServiceFactory` can be injected in order to interact with submissions from PHP, for example to import submissions from another source:

```php
use Wwwision\Neos\Submissions\Command\AddSubmission;
use Wwwision\Neos\Submissions\Factory\FormSubmissionServiceFactory;
use Wwwision\Neos\Submissions\Model\Preset\PresetId;
use Wwwision\Neos\Submissions\Model\Submission\SubmissionId;

// e.g. in a service or command controller with an injected $formSubmissionServiceFactory:
$service = $this->formSubmissionServiceFactory->create(PresetId::fromString('contact'));
$service->handleAddSubmission(AddSubmission::create(
    submissionId: SubmissionId::generate(),
    formId: 'legacy-contact-form',
    data: ['givenName' => 'John', 'familyName' => 'Doe', 'emailAddress' => 'john@example.com'],
));
```

The same service provides `handleArchiveSubmission()`, `handleArchiveUnprotectedSubmissions()`, `handleProtectSubmission()` and `handleUnprotectSubmission()` to change submissions as well as `findSubmissions()`, `getSubmission()` and `findForms()` to read data (archived submissions are excluded).

# Storage

Submissions are stored via Doctrine DBAL in one table per preset, using the default Flow database connection. MySQL/MariaDB, PostgreSQL and SQLite are supported.

| Column        | Description                                                    |
|---------------|----------------------------------------------------------------|
| `id`          | UUID of the submission (primary key)                           |
| `preset_id`   | id of the preset                                               |
| `form_id`     | the `formId` passed to the action                              |
| `form_label`  | generated form label                                           |
| `label`       | generated submission label                                     |
| `data`        | submitted data as JSON                                         |
| `protected`   | whether the submission is protected from being deleted         |
| `created_at`  | creation timestamp (UTC, `DATE_ATOM` format)                   |
| `archived_at` | archival ("deletion") timestamp, `NULL` if not archived        |

A different storage can be provided by implementing the `ForStoringSubmissions` port and a corresponding `ForStoringSubmissionsFactory`, and wiring it to the `FormSubmissionServiceFactory` via `Objects.yaml`.

# Contribution

Contributions in the form of [issues](https://github.com/bwaidelich/Wwwision.Neos.Submissions/issues) or [pull requests](https://github.com/bwaidelich/Wwwision.Neos.Submissions/pulls) are highly appreciated.

# License

See [LICENSE](./LICENSE)
