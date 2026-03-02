# PET – Entity Forms & Malleable Fields

## Purpose
Defines the standard implementation for entity creation and editing forms, specifically how they integrate "Malleable Fields" (dynamic, schema-driven properties).

## Form Architecture

### 1. Dual-Mode Support
All entity forms (`*Form.tsx`) MUST support both **Create** and **Edit** modes within a single component.
- **Props**:
  - `initialData` (optional): If provided, the form renders in "Edit" mode.
  - `onSuccess`: Callback triggered after successful save.
  - `onCancel`: Callback to close the form.
- **State Initialization**:
  - Fields are initialized from `initialData` or default values.
  - `isEditMode` boolean derived from `!!initialData`.

### 2. Standardized Layout
Forms follow a consistent visual hierarchy:
1. **Core Identity Fields**: Name, Email, Title (Standard React inputs).
2. **Classification Fields**: Category, Status (Select dropdowns).
3. **Malleable Fields Section**: A dedicated container rendering schema-driven fields.
4. **Action Bar**: "Save/Update" and "Cancel" buttons at the bottom.

## Malleable Fields Integration

### Concept
Malleable Fields allow entities to have dynamic properties defined by a JSON schema stored in the database. This enables admins to add fields (e.g., "Department" for Employees) without code changes.

### Implementation
- **Renderer**: `MalleableFieldsRenderer.tsx` is the standard component for rendering these fields.
- **Schema Fetching**: Forms fetch the *active* schema for their entity type on mount.
  - Endpoint: `/pet/v1/schemas/{type}?status=active`
- **Data Binding**:
  - Forms maintain a `malleableData` state object (Record<string, any>).
  - The renderer receives `values={malleableData}` and updates it via `onChange`.

### Code Pattern
```tsx
// State
const [malleableData, setMalleableData] = useState<Record<string, any>>(initialData?.malleableData || {});
const [activeSchema, setActiveSchema] = useState<SchemaDefinition | null>(null);

// Fetch Schema
useEffect(() => {
  fetch(`${apiUrl}/schemas/employee?status=active`)
    .then(res => res.json())
    .then(data => setActiveSchema(data[0]));
}, []);

// Render
{activeSchema && (
  <div className="malleable-section">
    <h4>Additional Information</h4>
    <MalleableFieldsRenderer 
      schema={activeSchema}
      values={malleableData}
      onChange={handleMalleableChange}
    />
  </div>
)}
```

## Supported Entities
The following entities currently support this pattern:
- **Customers**: `CustomerForm.tsx`
- **Projects**: `ProjectForm.tsx`
- **Quotes**: `QuoteForm.tsx`
- **Employees**: `EmployeeForm.tsx`
- **Knowledge (Articles)**: `ArticleForm.tsx`
- **Support (Tickets)**: `TicketForm.tsx`

## API Interaction
- **Create**: POST to `/pet/v1/{resource}`
- **Update**: PUT to `/pet/v1/{resource}/{id}`
- **Payload**: Both operations include `malleableData` object in the JSON body.

**Authority**: Normative
