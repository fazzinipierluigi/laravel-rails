# Laravel Rails

A Laravel package for building and executing workflows (state machines) inside your application. Define multi-step processes, route entities through states, trigger actions automatically, and visualize execution history — all through a declarative graph editor.

**Supported Laravel versions:** 10, 11, 12, 13  
**PHP:** 8.1+  
**Package name:** `fazzinipierluigi/laravel-rails`

---

## Table of Contents

1. [Core Concepts](#core-concepts)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Defining Workflows](#defining-workflows)
5. [States](#states)
6. [Transitions](#transitions)
7. [Actions](#actions)
8. [Instances](#instances)
9. [Form System](#form-system)
10. [Variable System](#variable-system)
11. [Expression Parser](#expression-parser)
12. [Conditional Routing](#conditional-routing)
13. [Auto-Advance](#auto-advance)
14. [Execution Logging](#execution-logging)
15. [Execution History Viewer](#execution-history-viewer)
16. [Permission System](#permission-system)
17. [Trigger System](#trigger-system)
18. [Built-in Actions](#built-in-actions)
19. [Blade Directives](#blade-directives)
20. [HTTP API Reference](#http-api-reference)
21. [Console Commands](#console-commands)
22. [Entity Integration](#entity-integration)
23. [Visual Editor](#visual-editor)
24. [Architecture Overview](#architecture-overview)
25. [Testing](#testing)

---

## Core Concepts

### Glossary

| Term | Description |
|---|---|
| **Workflow** | A named graph of states and transitions. Identified by a slug. |
| **State** | A node in the workflow graph. Can be `simple` (waits for user action) or `conditional` (auto-routes). Has `is_start` and `is_end` flags. |
| **Transition** | A directed edge from one state to another. Has conditions, actions, optional form, permissions. |
| **Action** | A PHP class that executes at a lifecycle phase: `on_enter`, `on_exit`, `pre`, or `post`. |
| **Instance** | One entity's progress through one workflow. Tracks the current state and variables. |
| **Trigger** | Mechanism that starts a workflow automatically: scheduled cron, Eloquent model event, or manual HTTP button. |
| **ExecutionLog** | Immutable append-only log of every event during an instance's lifecycle. |

### Data Model

```
Workflow ─────────────────────────────────────────────┐
  │ has_many                                           │
  ├── State[]                                          │
  │     │ has_many (morphMany via actionable)          │
  │     ├── Action[] (phase: on_enter | on_exit)       │
  │     └── Transition[] (from this state)             │
  │           │ has_many (morphMany via actionable)    │
  │           ├── Action[] (phase: pre | post)         │
  │           └── → to State (FK)                     │
  └── Instance[]                                       │
        │ belongs_to State (current)                   │
        │ morph_to    Entity (order, user, etc.)       │
        └── ExecutionLog[]                             │
```

---

## Installation

```bash
composer require fazzinipierluigi/laravel-rails
```

Run migrations to create all package tables:

```bash
php artisan automata:install
```

This runs `php artisan migrate` and registers the five built-in actions.

### Manual migration

```bash
php artisan migrate
```

### Tables created

| Table | Purpose |
|---|---|
| `workflows` | Workflow definitions |
| `states` | State nodes per workflow |
| `transitions` | Edges between states |
| `actions` | Action records (morphed to State or Transition) |
| `instances` | Per-entity workflow progress |
| `registered_actions` | Catalogue of available action classes |
| `workflow_triggers` | Trigger definitions (scheduled/manual/entity_event) |
| `execution_logs` | Immutable event log per instance |

---

## Configuration

Publish the config:

```bash
php artisan vendor:publish --tag=laravel-rails.config
```

`config/laravel-rails.php`:

```php
return [
    /*
    |--------------------------------------------------------------------------
    | Permission Resolver
    |--------------------------------------------------------------------------
    | Custom PermissionResolverInterface implementation.
    | If null, auto-detected: just-a-gate → spatie/laravel-permission → Laravel Gate.
    */
    'permission_resolver' => null,
];
```

---

## Defining Workflows

Workflows are created via the visual editor (see [Visual Editor](#visual-editor)) or programmatically:

```php
use Fazzinipierluigi\LaravelRails\Models\Workflow;
use Fazzinipierluigi\LaravelRails\Models\State;
use Fazzinipierluigi\LaravelRails\Models\Transition;

$workflow = Workflow::create(['name' => 'Order Approval', 'slug' => 'order-approval']);

$start    = State::create(['workflow_id' => $workflow->id, 'name' => 'Pending',  'slug' => 'pending',  'is_start' => true]);
$review   = State::create(['workflow_id' => $workflow->id, 'name' => 'Review',   'slug' => 'review']);
$approved = State::create(['workflow_id' => $workflow->id, 'name' => 'Approved', 'slug' => 'approved', 'is_end' => true]);
$rejected = State::create(['workflow_id' => $workflow->id, 'name' => 'Rejected', 'slug' => 'rejected', 'is_end' => true]);

Transition::create(['from' => $start->id,  'to' => $review->id,   'sort' => 0]);
Transition::create(['from' => $review->id, 'to' => $approved->id, 'sort' => 0]);
Transition::create(['from' => $review->id, 'to' => $rejected->id, 'sort' => 1]);
```

Or import from a JSON file:

```bash
php artisan automata:workflow:update path/to/workflow.json
```

---

## States

### Properties

| Column | Type | Description |
|---|---|---|
| `id` | UUID | Primary key |
| `workflow_id` | UUID | FK to workflows |
| `name` | string | Display name |
| `slug` | string | URL-safe identifier |
| `code` | string? | Sortable code (e.g. `step_01`) |
| `type` | string | `simple` (default) or `conditional` |
| `is_start` | bool | Entry point of the workflow |
| `is_end` | bool | Terminal state (triggers instance.completed) |
| `x`, `y` | float | Visual editor position |
| `view_permissions` | JSON? | Who can see this state |
| `view_operator` | string | `OR` (default) or `AND` |

### State types

**Simple state** — waits for an explicit `$instance->progress()` call or form submission.

**Conditional state** — auto-traversed immediately on entry. The first transition whose `execute_condition` evaluates to `true` is followed. A transition with no condition acts as the else/default branch.

### Code comparison helpers

States with codes like `step_01`, `step_02` can be compared:

```php
$state->codeGT('step_01');  // current code > step_01
$state->codeGTE('step_01'); // >=
$state->codeLT('step_05');  // <
$state->codeEQ('step_03');  // ==
```

### Permission check

```php
$state->canView($user);   // Returns bool. Null $user → false if permissions set.
```

---

## Transitions

### Properties

| Column | Type | Description |
|---|---|---|
| `id` | UUID | Primary key |
| `from` | UUID | FK to states (source) |
| `to` | UUID | FK to states (destination) |
| `sort` | int | Evaluation order (lower = evaluated first) |
| `label` | string? | Display label (used in editor and conditional branches) |
| `show_condition` | JSON? | JsonLogic: controls visibility in UI |
| `execute_condition` | JSON? | JsonLogic: controls whether transition can fire |
| `exit_condition` | JSON? | JsonLogic: controls whether source state can be exited |
| `permission` | string? | Legacy single permission string |
| `redirect` | string? | Named route to redirect to after transition |
| `form_type` | string? | `json` or `html` |
| `form_data` | text? | Form schema (JSON array) or raw HTML |
| `view_permissions` | JSON? | Who can see this transition |
| `view_operator` | string | `OR` or `AND` |
| `advance_permissions` | JSON? | Who can execute this transition |
| `advance_operator` | string | `OR` or `AND` |

### Conditions

All three condition columns accept [JsonLogic](https://jsonlogic.com/) rules evaluated against a context object:

```json
{
  "variables": { "status": "approved", "amount": 500 },
  "entity":    { "id": "uuid", "type": "Order" },
  "request":   { "action": "approve" },
  "instance":  { "id": "uuid", "workflow_id": "uuid", "state_id": "uuid" }
}
```

Example — only fire if amount is over 1000:
```json
{ ">": [{ "var": "variables.amount" }, 1000] }
```

### Permission checks

```php
$transition->can($instance);         // State match + advance permission + execute_condition
$transition->can_show($instance);    // State match + view permission + show_condition
$transition->can_exit($instance);    // exit_condition only
$transition->canAdvance($user);      // Advance permission only (no instance needed)
```

---

## Actions

Actions are PHP classes that implement `ActionInterface`:

```php
use Fazzinipierluigi\LaravelRails\Interfaces\ActionInterface;

class MyAction implements ActionInterface
{
    public static string $display_name = 'My Action';

    public static array $configuration_schema = [
        ['name' => 'message', 'type' => 'text', 'label' => 'Message', 'required' => true],
    ];

    public function execute($instance, $entity, ?array $configuration, $destination_state): bool
    {
        // Return true = success, false = failure (throws exception in workflow)
        return true;
    }
}
```

### Lifecycle phases

| Phase | When | Attached to |
|---|---|---|
| `on_enter` | When the destination state is entered | State |
| `on_exit` | When the source state is exited | State |
| `pre` | Before the state change | Transition |
| `post` | After the state change (destination state already active) | Transition |

### Registering custom actions

```bash
php artisan automata:action:register "App\Rails\Actions\MyAction"
```

Or generate a stub:
```bash
php artisan rail:make-action SendApprovalEmail
# Creates app/Rails/Actions/SendApprovalEmail.php
```

---

## Instances

An instance tracks a single entity's progress through a workflow.

### Starting a workflow

```php
$workflow = Workflow::getBySlug('order-approval');
$instance = $workflow->instantiate($order, 'user:' . auth()->id());
```

The second argument is an optional `triggeredBy` label that appears in execution logs. Defaults to `'system'`.

**Throws** `\Exception` if:
- Workflow has no start state
- This entity already has an active instance for this workflow

### Progressing

```php
$redirectUrl = $instance->progress();         // system-triggered
$redirectUrl = $instance->progress('user:42'); // user-triggered
```

`progress()` finds the first applicable transition from the current state and executes it. Returns the redirect URL (from `transition->redirect` route, or `url()->previous()`).

**Throws** `\Exception` if no applicable transition exists.

### Reading state

```php
$instance->state;        // Current State model
$instance->state_id;     // Current State UUID
$instance->workflow;     // Workflow model
$instance->entity;       // The entity (Order, User, etc.)
$instance->variables;    // Array of workflow variables
```

---

## Form System

Transitions can require form input before advancing. Two modes:

### JSON schema forms

The form is defined as an array of field descriptors:

```json
[
  {"name": "notes",    "type": "textarea", "label": "Notes",    "required": false},
  {"name": "approved", "type": "checkbox", "label": "Approve?", "required": true},
  {"name": "amount",   "type": "number",   "label": "Amount",   "required": true, "validation": "min:0"}
]
```

**Supported field types:** `text`, `email`, `number`, `password`, `textarea`, `select`, `checkbox`, `radio`, `date`, `hidden`

**Options fields** (for `select` and `radio`):
```json
{"name": "priority", "type": "select", "options": [{"value": "low", "label": "Low"}, {"value": "high", "label": "High"}]}
```

**Custom validation** (any Laravel validation rules):
```json
{"name": "amount", "type": "number", "validation": "min:0|max:10000"}
```

### HTML forms

Set `form_type = 'html'` and provide raw HTML in `form_data`. The HTML is wrapped in a `<form>` element automatically.

### Rendering forms

Use the Blade directive anywhere in a view:

```blade
@laravel_rail_form($transition->id, $instance->id)
{{-- Or with options: --}}
@laravel_rail_form($transition->id, $instance->id, ['submit_label' => 'Submit', 'form_class' => 'my-form'])
```

The renderer:
1. Checks view permissions — returns empty string if user cannot see this transition
2. Emits `<style>` CSS only once per page (static deduplication)
3. Wraps content in `<form method="POST">` pointing to the execute endpoint
4. Injects CSRF token and `instance_id` hidden field

### Form submission

Forms POST to `POST /laravel-rails/transition/{transitionId}/execute` with at minimum `instance_id`.

The `FormController::execute()` method:
1. Validates form data against the JSON schema (required fields, email/number/date type rules, custom validation)
2. Merges all non-token POST fields into `instance->variables`
3. Checks advance permissions (403 if denied)
4. Executes the specific transition (calls `transition->perform($instance)`)
5. Resolves any conditional chain after the transition
6. Dispatches auto-advance job if the new state qualifies
7. Redirects to `transition->redirect` route, or `url()->previous()`

---

## Variable System

Instances carry a `variables` JSON column — a free-form key-value store updated by form submissions and actions.

### Template resolution

The `VariableResolver` replaces `{{token}}` placeholders in strings:

```php
use Fazzinipierluigi\LaravelRails\Classes\VariableResolver;

$resolved = VariableResolver::resolve('Hello {{variables.name}}, total: {{variables.total}}', $instance, $entity);
```

### Supported tokens

| Token | Resolves to |
|---|---|
| `{{now}}` | Current datetime: `Y-m-d H:i:s` |
| `{{today}}` | Current date: `Y-m-d` |
| `{{instance.id}}` | Instance UUID |
| `{{instance.workflow_id}}` | Workflow UUID |
| `{{instance.state_id}}` | Current state UUID |
| `{{variables.key}}` | `$instance->variables['key']` (dot-notation) |
| `{{entity.field}}` | `$entity->field` (dot-notation) |
| `{{key}}` | Shorthand: checks variables first, then entity attributes |

Spaces around the token name are trimmed: `{{ key }}` works.

### Instance variable helpers

```php
$instance->getVariable('price');           // dot-notation get, returns null if missing
$instance->getVariable('price', 0);        // with default
$instance->setVariable('status', 'active'); // dot-notation set + save
$instance->mergeVariables(['a' => 1, 'b' => 2]); // merge + save
$instance->hasVariable('price');           // bool
```

### JsonLogic context

```php
$context = VariableResolver::buildContext($instance, $entity);
// Returns:
// [
//   'variables' => ['price' => 99, ...],
//   'entity'    => ['id' => '...', 'status' => 'pending', ...],
//   'request'   => [...],  // current request data
//   'instance'  => ['id' => '...', 'workflow_id' => '...', 'state_id' => '...'],
// ]
```

This context is passed to all JsonLogic condition evaluations.

---

## Expression Parser

Used by `SetVariableWithFormula`. A lightweight math expression evaluator.

### Supported syntax

- Numeric literals: `42`, `3.14`
- String literals: `"hello"` (concatenate with `+`)
- Operators: `+`, `-`, `*`, `/`, `^` (power), `%` (modulo)
- Parentheses: `(a + b) * c`
- Variable names (case-insensitive): `quantity`, `unit_price`
- Function calls: `abs(x)`, `round(x, 2)`, `floor(x)`, `ceil(x)`, `sqrt(x)`, `pow(x,y)`, `max(a,b,...)`, `min(a,b,...)`

Variables are resolved via `onVariable` callback set by the caller. Unknown variables default to 0.

**Example:**
```php
$parser = new \Fazzinipierluigi\LaravelRails\Classes\Expression\Parser();
$parser->onVariable = fn($name, &$val) => $val = $instance->getVariable($name) ?? 0;
$result = $parser->execute('quantity * unit_price * (1 + tax_rate)');
```

---

## Conditional Routing

A **conditional state** (`type = 'conditional'`) is traversed automatically without human interaction. Transitions are evaluated in `sort` order; the first one whose `execute_condition` is satisfied fires. A transition with no `execute_condition` is always satisfied (else/default branch).

### Visual representation

In the editor, conditional states appear as amber diamond nodes. Each output slot corresponds to one branch transition.

### Infinite loop detection

`Instance::resolveConditionalChain()` tracks visited state IDs within a single chain traversal. If a conditional state is revisited, an `\Exception` is thrown with message `"Infinite loop detected"`. A loop through a simple state is allowed (the simple state breaks the chain and requires explicit `progress()`).

### Max hops

The default maximum chain length is 50 hops. Exceeding it throws `"Maximum conditional chain length exceeded"`.

---

## Auto-Advance

States where **no outgoing transition has a form** are automatically advanced via a queued job, enabling fully automated pipeline stages.

### Trigger logic

`Instance::checkAutoAdvance()` is called:
- After `Workflow::instantiate()` (entry into start state)
- After `Instance::progress()` (entry into next state)
- After `FormController::execute()` (entry into state after form submission)

It dispatches `AutoAdvanceWorkflow` if:
1. Current state is not an end state
2. Current state is not conditional (conditional states self-route without jobs)
3. State has at least one outgoing transition
4. No outgoing transition has a form

### The job

`AutoAdvanceWorkflow` is a `ShouldQueue` job with `$tries = 3` and `$backoff = 5` seconds.

When the job runs, it **re-checks** all conditions at execution time to handle race conditions: if the state has changed (another process advanced it), or a form has been added since dispatch, the job exits silently.

### Queue configuration

Ensure a queue worker is running:

```bash
php artisan queue:work
```

If `queue.default` is `sync` (e.g. in tests), the job runs synchronously on dispatch.

---

## Execution Logging

Every meaningful event during workflow execution is written to the `execution_logs` table by `ExecutionLogger`.

**Key design principle:** Logging is fire-and-forget. Logging failures are caught and silently forwarded to Laravel's log. They never propagate to the workflow.

### Event types

| Event | Subject | When |
|---|---|---|
| `instance.started` | — | Workflow instantiated |
| `state.entered` | state | Instance enters a state |
| `state.exited` | state | Instance leaves a state |
| `transition.performed` | transition | Transition executed |
| `transition.condition_evaluated` | transition | A condition was checked |
| `action.executed` | state OR transition | An action ran |
| `instance.completed` | state | End state reached |
| `instance.blocked` | state | Blocked state detected |
| `execution.error` | — | Unhandled exception |
| `permission.denied` | transition | Permission check failed |

### Log record structure

```php
ExecutionLog {
    id:           UUID
    instance_id:  UUID (FK)
    event:        string
    subject_type: 'state' | 'transition' | 'action' | null
    subject_id:   UUID | null
    data:         array  // full event details
    triggered_by: string // 'user:42', 'system', 'auto', 'trigger:uuid'
    occurred_at:  datetime
}
```

### `triggered_by` values

| Format | Meaning |
|---|---|
| `system` | Default, background system call |
| `user:{id}` | Auth user with given ID |
| `auto` | Auto-advance job |
| `trigger:{id}` | Workflow trigger by UUID |

### Querying logs

```php
ExecutionLog::forInstance($instanceId)->get();
ExecutionLog::forInstance($instanceId)->ofEvent('state.entered')->get();
ExecutionLog::forInstance($instanceId)->forState($stateId)->get();
ExecutionLog::forInstance($instanceId)->forTransition($transitionId)->get();
```

### Logger per instance

Each `Instance` caches its logger for the request duration:

```php
$logger = $instance->logger();          // 'system' triggered_by
$logger = $instance->logger('user:42'); // first call locks triggered_by
```

Subsequent calls return the same logger instance regardless of the `triggeredBy` argument.

---

## Execution History Viewer

A visual read-only replay of a workflow instance's journey, built on litegraph.js.

### Usage

```blade
@laravel_rail_execution($instance->id)
```

### Node coloring

| Color | Meaning |
|---|---|
| Blue (`#1d4ed8`) | Current state |
| Dark green (`#166534`) | Visited state |
| Red (`#991b1b`) | State with action failure or execution error |
| Gray (`#1e293b`) | Unreached state |

Link colors follow the same scheme for transitions.

### Interactivity

- **Click a state node** → opens modal showing lifecycle events (state.entered, state.exited, instance.completed) and actions (on_enter, on_exit) with result badges
- **Click a transition edge** → opens modal showing condition evaluations, pre/post actions, and permission denial events
- **Permission denied events** render in red with full details

### Data API

The viewer loads data from:

```
GET /laravel-rails/api/execution/{instanceId}
```

And loads per-node logs from:

```
GET /laravel-rails/api/execution/{instanceId}/{state|transition}/{subjectId}
```

---

## Permission System

### Architecture

The system uses a **strategy pattern** with a `PermissionResolverInterface`. The service provider auto-detects the available permission package at singleton resolution time.

**Cascading resolver priority:**
1. Custom resolver from `config('laravel-rails.permission_resolver')`
2. `fazzinipierluigi/just-a-gate` — if class exists
3. `spatie/laravel-permission` — if `PermissionServiceProvider` class exists
4. Vanilla Laravel `Gate` — always available fallback

### Interface

```php
interface PermissionResolverInterface
{
    public function check(mixed $user, array $permissions, string $operator = 'OR'): bool;
    public function getDriverName(): string;
}
```

### Custom resolver

```php
// config/laravel-rails.php
'permission_resolver' => App\Permissions\MyResolver::class,
```

```php
class MyResolver implements PermissionResolverInterface
{
    public function check(mixed $user, array $permissions, string $operator = 'OR'): bool
    {
        // Your logic here
    }
    public function getDriverName(): string { return 'my-driver'; }
}
```

### Operator logic

- **OR** (default): any one permission in the array suffices
- **AND**: all permissions in the array must be satisfied

### Permission checks by resource

**State visibility:**
```php
$state->canView($user);    // Uses view_permissions + view_operator
```

**Transition visibility:**
```php
$transition->can_show($instance); // view_permissions + view_operator + show_condition
```

**Transition advance:**
```php
$transition->can($instance);       // advance_permissions + advance_operator + execute_condition
$transition->canAdvance($user);    // advance_permissions only (no instance)
```

### System/auto bypass

When `auth()->user()` returns `null` (queue job context, system action), **advance permission checks are bypassed**. This allows auto-advance jobs to move instances forward without an authenticated user. View permission checks return `false` when no user is present and permissions are configured (no UI rendered in system context).

### Permission denial logging

Every denied permission check is written to `execution_logs` as a `permission.denied` event, including:
- Which user was denied
- Which transition
- Which permission driver reported the denial
- Full context for the execution viewer

---

## Trigger System

Triggers start a workflow instance automatically based on three mechanism types.

### Trigger model

```php
WorkflowTrigger {
    id:            UUID
    workflow_id:   UUID (FK)
    name:          string
    type:          'scheduled' | 'manual' | 'entity_event'
    configuration: JSON
    is_active:     bool
    last_run_at:   timestamp?
}
```

### Type: scheduled

Fires on a cron expression. Each matching entity gets a workflow instance (idempotent — entities with an existing instance are skipped).

**Configuration:**
```json
{
  "cron": "0 9 * * 1",
  "entity_class": "App\\Models\\Order",
  "entity_scope": "pending",
  "entity_condition": { "==": [{"var": "status"}, "pending"] }
}
```

Run scheduled triggers:
```bash
php artisan rail:trigger:scheduled [--dry-run]
```

Add to your scheduler:
```php
Schedule::command('rail:trigger:scheduled')->everyMinute();
```

### Type: manual

A button/form rendered in your view. Clicking it fires the trigger for a specific entity.

**Configuration:**
```json
{
  "entity_class": "App\\Models\\Order",
  "label": "Start Approval",
  "permission": "approve-orders",
  "button_class": "btn btn-primary"
}
```

**Render:**
```blade
@laravel_rail_trigger($trigger->id, $order->id)
```

**Fire programmatically:**
```php
$order->fireManualTrigger('trigger-name-or-uuid');
```

### Type: entity_event

Fires when an Eloquent model event occurs on the target class.

**Configuration:**
```json
{
  "entity_class": "App\\Models\\Order",
  "event": "created",
  "conditions": { "==": [{"var": "status"}, "draft"] }
}
```

**Supported events:** `created`, `updated`, `created_or_updated`

The service provider registers Eloquent observers on boot for all active `entity_event` triggers. Wrapped in try/catch to handle missing tables before migrations run.

---

## Built-in Actions

All built-in actions are registered via `automata:install`.

### SendEmail

Sends an HTML email via Laravel Mail. All string fields support `{{token}}` interpolation.

**Configuration schema:**
```json
{
  "to":       "{{entity.email}}",
  "subject":  "Order {{instance.id}} approved",
  "body":     "<p>Your order has been approved.</p>",
  "template": "emails.approval",
  "cc":       "manager@company.com"
}
```

`template` (Blade view) takes priority over `body`. Variables passed to the view: `instance`, `entity`, `variables`.

### SetVariableWithEntity

Maps entity attributes to instance variables.

**Configuration schema:**
```json
{
  "mappings": [
    {"variable": "customer_name",  "entity_field": "name"},
    {"variable": "customer_email", "entity_field": "email"},
    {"variable": "address.city",   "entity_field": "address.city"}
  ]
}
```

Both `variable` and `entity_field` support dot-notation. `mappings` can be a JSON string or a parsed array.

### SetVariableWithFormula

Evaluates a math formula and stores the result in an instance variable.

**Configuration schema:**
```json
{
  "variable": "total_price",
  "formula":  "quantity * unit_price * (1 + tax_rate)"
}
```

Formula variables are resolved from instance variables first, then entity attributes (case-insensitive). Unknown variables default to 0. Division by zero throws.

**Available math functions:** `abs`, `round`, `floor`, `ceil`, `sqrt`, `pow`, `max`, `min`

### StartSubprocess

Instantiates another workflow on the same entity. Idempotent — if the sub-workflow already has an instance for this entity, it is skipped.

**Configuration schema:**
```json
{
  "workflow_slug":       "sub-approval",
  "store_instance_id_as": "sub_instance_id",
  "copy_variables":      ["customer_name", "amount"],
  "initial_variables":   {"source": "parent-workflow"}
}
```

`store_instance_id_as`: if set, stores the new instance's UUID in this variable on the parent instance.

### WriteEntity

Writes resolved values back to entity fields and calls `save()`.

**Configuration schema:**
```json
{
  "mappings": [
    {"field": "status",      "value": "approved"},
    {"field": "approved_at", "value": "{{now}}"},
    {"field": "approved_by", "value": "{{variables.user_id}}"}
  ]
}
```

All `value` strings are resolved through `VariableResolver` before assignment.

---

## Blade Directives

### `@laravel_rail_editor`

Renders the full-screen litegraph.js workflow editor:

```blade
@laravel_rail_editor('order-approval')
```

### `@laravel_rail_form`

Renders the form for a transition (empty string if no form or no permission):

```blade
@laravel_rail_form($transitionId, $instanceId)
@laravel_rail_form($transitionId, $instanceId, ['submit_label' => 'Continue', 'form_class' => 'my-form'])
```

### `@laravel_rail_trigger`

Renders a manual trigger button:

```blade
@laravel_rail_trigger($triggerId, $entityId)
```

### `@laravel_rail_execution`

Renders the read-only execution history viewer:

```blade
@laravel_rail_execution($instanceId)
```

### `@laravel_rail_actions`

Renders the available transition actions for a workflow instance. Automatically reads the current state and shows only transitions whose `show_condition` and `view_permissions` pass for the current user.

- **0 visible transitions** → renders nothing (empty string).
- **1 visible transition** → renders the form (or a plain advance button) directly, no select.
- **2+ visible transitions** → renders a `<select>` to choose among them, plus one panel per transition (only the selected one shown at a time).

Styling is intentionally left to the host application. The package only provides structural HTML and stable CSS/data hooks.

#### Basic usage

```blade
@laravel_rail_actions($instance->id)
```

#### With options

```blade
@laravel_rail_actions($instance->id, [
    'wrapper_class' => 'my-actions',
    'select_class'  => 'form-select',
    'form_class'    => 'my-form',
    'btn_class'     => 'btn btn-primary',
])
```

#### Options

| Option | Default | Description |
|---|---|---|
| `wrapper_class` | `lr-actions` | CSS class on the outer wrapper `<div>` |
| `select_class` | `lr-actions-select` | CSS class on the `<select>` (multi-transition only) |
| `form_class` | `lr-form` | CSS class forwarded to the form element |
| `btn_class` | `lr-action-btn` | CSS class on plain advance `<button>` elements |
| `btn_label` | transition label or `Avanza` | Override label for all plain-advance buttons |

#### HTML structure

Single transition (no select):

```html
<div class="lr-actions" id="lr-{uid}">
  <div class="lr-action" data-transition="{transition-id}">
    <!-- form rendered by @laravel_rail_form, or: -->
    <form method="POST" action="..." class="lr-action-form">
      <input type="hidden" name="instance_id" value="...">
      <button type="submit" class="lr-action-btn" data-transition="{id}">Label</button>
    </form>
  </div>
</div>
```

Multiple transitions:

```html
<div class="lr-actions" id="lr-{uid}">
  <select class="lr-actions-select" id="lr-{uid}-select">
    <option value="{transition-id}">Label A</option>
    <option value="{transition-id}">Label B</option>
  </select>

  <div class="lr-action" id="lr-{uid}-{transition-id}" data-transition="{id}">
    <!-- first panel: visible -->
    ...
  </div>
  <div class="lr-action" id="lr-{uid}-{transition-id}" data-transition="{id}" style="display:none">
    <!-- subsequent panels: hidden until selected -->
    ...
  </div>

  <script>/* _lrSwitch inline helper */</script>
</div>
```

#### CSS hooks

| Selector | Element | Notes |
|---|---|---|
| `.lr-actions` | Outer wrapper | One per directive call |
| `.lr-actions-select` | `<select>` | Only when 2+ transitions |
| `.lr-action` | Panel per transition | Has `data-transition="{id}"` |
| `.lr-action-form` | Plain-advance `<form>` | Only when transition has no custom form |
| `.lr-action-btn` | Plain-advance `<button>` | Has `data-transition="{id}"` |

#### Styling example (Bootstrap 5)

```blade
@laravel_rail_actions($instance->id, [
    'select_class' => 'form-select mb-3',
    'btn_class'    => 'btn btn-primary',
])
```

```css
.lr-actions { margin-top: 1.5rem; }
.lr-action-form { display: flex; gap: .5rem; flex-wrap: wrap; }
```

#### Using `availableTransitions()` directly

For custom rendering beyond what the directive provides:

```php
$transitions = $instance->availableTransitions();
// Returns Collection<Transition>, ordered by sort, filtered by can_show()
```

---

## HTTP API Reference

All routes are prefixed with `/laravel-rails` and use `web` middleware.

### Visual Editor

| Method | URL | Controller | Description |
|---|---|---|---|
| GET | `/laravel-rails/api/workflow/{slug}` | EditorController@show | Get full workflow JSON |
| PUT | `/laravel-rails/api/workflow/{slug}` | EditorController@update | Save workflow from editor |
| GET | `/laravel-rails/api/registered-actions` | EditorController@registeredActions | List available action classes |

#### GET `/api/workflow/{slug}` response

```json
{
  "id": "uuid",
  "name": "Order Approval",
  "slug": "order-approval",
  "states": [
    {
      "id": "uuid",
      "name": "Start",
      "type": "simple",
      "slug": "start",
      "is_start": true,
      "is_end": false,
      "x": 100,
      "y": 200,
      "view_permissions": [],
      "view_operator": "OR",
      "on_enter_actions": [],
      "on_exit_actions": [],
      "transitions": [
        {
          "id": "uuid",
          "to_id": "uuid",
          "sort": 0,
          "label": null,
          "show_condition": null,
          "execute_condition": null,
          "exit_condition": null,
          "form_type": "json",
          "form_data": "[...]",
          "view_permissions": [],
          "view_operator": "OR",
          "advance_permissions": ["approve-orders"],
          "advance_operator": "OR",
          "actions": []
        }
      ]
    }
  ]
}
```

### Form Execution

| Method | URL | Controller | Description |
|---|---|---|---|
| POST | `/laravel-rails/transition/{transitionId}/execute` | FormController@execute | Submit form and advance transition |

**Request body:**
```
instance_id = {instanceId}
{field_name} = {field_value}
...
```

**Response (HTML redirect):** `302 Location: {redirect_url}`  
**Response (JSON, if `Accept: application/json`):**
```json
{"redirect": "https://example.com/orders/123"}
```

**Error responses:**
- `404` — transition or instance not found
- `403` — advance permission denied
- `422` — form validation errors

### Trigger

| Method | URL | Controller | Description |
|---|---|---|---|
| POST | `/laravel-rails/trigger/{triggerId}/fire` | TriggerController@fire | Fire a manual trigger |

### Execution Viewer

| Method | URL | Controller | Description |
|---|---|---|---|
| GET | `/laravel-rails/api/execution/{instanceId}` | ExecutionController@data | Full execution data |
| GET | `/laravel-rails/api/execution/{instanceId}/{type}/{subjectId}` | ExecutionController@nodeLogs | Logs for a specific node |

`type` must be `state` or `transition`. `subjectId` must be a UUID.

#### GET `/api/execution/{instanceId}` response

```json
{
  "instance": {
    "id": "uuid",
    "workflow_id": "uuid",
    "current_state_id": "uuid",
    "instanceable_type": "App\\Models\\Order",
    "instanceable_id": "uuid"
  },
  "workflow": {
    "id": "uuid",
    "name": "Order Approval",
    "states": [
      {
        "id": "uuid",
        "name": "Start",
        "type": "simple",
        "is_start": true,
        "is_end": false,
        "code": "step_01",
        "x": 100,
        "y": 200,
        "transitions": [
          {"id": "uuid", "from": "uuid", "to": "uuid", "label": "Approve", "sort": 0}
        ]
      }
    ]
  },
  "traversed": {
    "states":      ["uuid", "uuid"],
    "transitions": ["uuid"],
    "errors": {
      "states":      [],
      "transitions": []
    }
  }
}
```

### Static Assets

| Method | URL | Description |
|---|---|---|
| GET | `/laravel-rails/assets/jsonlogic_ui.js` | JsonLogic UI JavaScript |
| GET | `/laravel-rails/assets/jsonlogic_ui.css` | JsonLogic UI CSS |

---

## Console Commands

| Command | Description |
|---|---|
| `php artisan automata:install` | Run migrations + register built-in actions |
| `php artisan automata:action:register {class}` | Register an action class |
| `php artisan automata:workflow:export` | Export workflow to JSON |
| `php artisan automata:workflow:update {file}` | Import/update workflow from JSON |
| `php artisan rail:make-action {Name}` | Generate `app/Rails/Actions/{Name}.php` stub |
| `php artisan rail:trigger:scheduled [--dry-run]` | Fire all due scheduled triggers |

---

## Entity Integration

Add the `IsWorkflowEntity` trait to any Eloquent model to enable workflow helper methods:

```php
use Fazzinipierluigi\LaravelRails\Traits\IsWorkflowEntity;

class Order extends Model
{
    use IsWorkflowEntity;
}
```

### Available methods

```php
// Start the workflow for this entity
$order->startWorkflow('order-approval'); // workflow slug

// Get the current workflow instance
$instance = $order->getWorkflowInstance('order-approval');

// Advance the workflow
$redirectUrl = $order->progressWorkflow('order-approval');

// Fire a manual trigger
$order->fireManualTrigger('trigger-name-or-uuid');
```

`startWorkflow()` passes `'user:{auth()->id()}'` or `'system'` as `triggeredBy`.

---

## Visual Editor

The editor is powered by [litegraph.js](https://github.com/jagenjo/litegraph.js) and provides a fullscreen node-based canvas.

### Node types

| Node | Appearance | Represents |
|---|---|---|
| `workflow/state` | Blue rectangle | Simple state |
| `workflow/conditional` | Amber diamond | Conditional routing state |

### Editor panels

**Node panel** (right sidebar when state selected):
- Name, code, type selector
- On-enter / on-exit action list with action class selector
- View permissions (comma-separated) + OR/AND operator
- End state checkbox

**Link panel** (right sidebar when transition selected):
- Label
- Show / execute / exit condition (JsonLogic UI)
- Form type (none / JSON builder / HTML)
- JSON field builder with type selector, label, placeholder, required, validation, options
- Live form preview
- View permissions + operator
- Advance permissions + operator
- Redirect route name

### Toolbar

- `▷ Start` — mark selected node as start state
- `◼ End` — mark selected node as end state
- `◇ Condizionale` — add a conditional node
- `💾 Salva` — save workflow via PUT API

### JsonLogic condition editor

The condition editor (`jsonlogic_ui.js`) provides a visual rule builder with:
- Variable path selector (from a suggested list + custom)
- Operator selector: `==`, `!=`, `>`, `>=`, `<`, `<=`, `in`, `!`, `truthy`
- Value type selector: string, number, boolean, null, variable path
- AND/OR group nesting
- NOT group modifier

---

## Architecture Overview

### Service Provider

`LaravelRailsServiceProvider` handles:
- `loadMigrationsFrom()` — registers all package migrations
- `loadViewsFrom()` — registers Blade views under `laravel-rails::` namespace
- `loadRoutesFrom()` — registers all HTTP routes
- `Blade::directive()` — registers all four Blade directives
- `app->singleton(PermissionResolverInterface::class)` — auto-detects and binds resolver
- `app->booted()` callback — registers Eloquent observers for entity_event triggers
- Console commands registration

### Request lifecycle (form submission)

```
User POSTs form
  → FormController::execute()
     → Find Transition + Instance (404 if missing)
     → Validate form data against JSON schema
     → Merge form fields into instance->variables
     → Initialize ExecutionLogger with 'user:{id}' triggered_by
     → Check advance permission (403 if denied)
     → Transition::perform($instance)
         → Check can($instance) [state match + advance perm + execute_condition]
         → Execute pre actions
         → Check can_exit($instance) [exit_condition]
         → Log state.exited
         → Execute on_exit actions on source state
         → Log transition.performed
         → Update instance.state_id = transition.to, save
         → Log state.entered
         → Execute on_enter actions on destination state
         → If is_end → log instance.completed
         → Execute post actions
     → Instance::resolveConditionalChain()
         → While current state is conditional:
             → Find first matching transition (execute_condition)
             → Execute that transition (perform)
             → Repeat
     → Instance::checkAutoAdvance()
         → If no forms on outgoing transitions → dispatch AutoAdvanceWorkflow job
     → Redirect
```

### Auto-advance job lifecycle

```
AutoAdvanceWorkflow::dispatch($instanceId) [queued, 3 tries, 5s backoff]
  → handle()
     → Find Instance (return if missing)
     → Refresh from DB
     → Re-check: not end, not conditional, has transitions, no forms
     → Instance::progress('auto')
         → [same as above from Transition::perform...]
```

### Permission resolution (VanillaGateResolver)

```
$resolver->check($user, ['ability-a', 'ability-b'], 'OR')
  → Gate::forUser($user)->check('ability-a') → true  → return true (OR short-circuit)

$resolver->check($user, ['ability-a', 'ability-b'], 'AND')
  → Gate::forUser($user)->check('ability-a') → true
  → Gate::forUser($user)->check('ability-b') → false → return false (AND short-circuit)
```

---

## Testing

### Test setup

The package uses [Orchestra Testbench](https://packages.tools/testbench/) for testing.

```bash
composer install
./vendor/bin/phpunit
```

### Test structure

```
tests/
├── TestCase.php                         # Base: Testbench + in-memory SQLite + migrations
├── Support/
│   ├── Models/
│   │   ├── Order.php                    # Test entity (UUID pk, fillable)
│   │   └── User.php                     # Auth test user (extends Authenticatable)
│   ├── Actions/
│   │   ├── AlwaysSucceedAction.php      # Always returns true
│   │   └── AlwaysFailAction.php         # Always returns false
│   └── WorkflowFactory.php             # Creates reusable workflow structures
├── Unit/
│   ├── ExpressionParserTest.php         # Math expression parser
│   └── VariableResolverTest.php         # Template variable resolution
└── Feature/
    ├── WorkflowLifecycleTest.php        # instantiate → progress → end state
    ├── ConditionalStateTest.php         # Conditional routing and chain resolution
    ├── AutoAdvanceTest.php              # Queue dispatch + job idempotency
    ├── ActionExecutionTest.php          # All four action phases + built-in actions
    ├── ExecutionLoggerTest.php          # All event types + fire-and-forget safety
    ├── PermissionTest.php               # VanillaGateResolver + all permission checks
    ├── FormRendererTest.php             # JSON schema rendering + permission visibility
    └── Http/
        ├── EditorControllerTest.php     # GET/PUT workflow + registered-actions
        ├── FormControllerTest.php       # Form submission, validation, permission
        └── ExecutionControllerTest.php  # Execution data + node logs API
```

### TestCase base class

```php
class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [LaravelRailsServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite', 'database' => ':memory:',
        ]);
        $app['config']->set('session.driver', 'array');
        $app['config']->set('queue.default', 'sync');
    }

    protected function setUp(): void
    {
        parent::setUp();
        Model::unguard();       // Allow ::create() calls
        $this->artisan('migrate'); // Run all package migrations
        // Create test entity tables (orders, users)
    }
}
```

### WorkflowFactory helpers

```php
WorkflowFactory::twoState($slug)        // Start → End
WorkflowFactory::threeState($slug)      // Start → Middle → End
WorkflowFactory::withForm($slug)        // Start -[form]-> Review → End
WorkflowFactory::withConditional($slug) // Start → Conditional → Branch A/B → End
WorkflowFactory::createOrder($attrs)    // Create test Order entity
WorkflowFactory::addAction($model, $phase, $actionClass, $config)
```

### Testing permission checks

```php
$this->app->instance(PermissionResolverInterface::class, new VanillaGateResolver());
Gate::define('my-ability', fn($user) => true);
$this->actingAs($user);
```

### Testing queue dispatch

```php
Queue::fake();
$workflow->instantiate($order);
Queue::assertDispatched(AutoAdvanceWorkflow::class);
```

### Testing the auto-advance job directly

```php
Queue::fake();
$instance = $workflow->instantiate($order);
$job = new AutoAdvanceWorkflow($instance->id);
$job->handle();
$instance->refresh();
$this->assertEquals($end->id, $instance->state_id);
```

---

## FAQ

### Why is my auto-advance job not firing?

Check that:
1. The current state has at least one outgoing transition
2. No outgoing transition has `form_type` set
3. The state is not `is_end = true`
4. The state `type` is `simple` (not `conditional`)
5. A queue worker is running (`php artisan queue:work`)

### Why is my conditional chain deadlocking?

Every conditional state must have at least one transition with no `execute_condition` (the else/default branch). If all transitions have conditions and none match, an exception is thrown.

### How do I debug permission issues?

Check `execution_logs` for `permission.denied` events. They include the user ID, transition ID, denied permissions, and which driver reported the denial.

### Can I use multiple workflows for the same entity?

Yes. Each `(entity_type, entity_id, workflow_id)` combination can have at most one instance. The same entity can simultaneously be in multiple different workflows.

### How do I customize the form submit button?

```blade
@laravel_rail_form($transitionId, $instanceId, ['submit_label' => 'Approve', 'form_class' => 'custom-form'])
```

---

*This documentation was generated for `fazzinipierluigi/laravel-rails` version as of 2026-06-05.*
