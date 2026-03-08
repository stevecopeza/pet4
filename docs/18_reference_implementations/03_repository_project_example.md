# Reference Implementation – Project Repository

## Purpose
Shows how a domain aggregate is persisted without Active Record or WordPress leakage.

---

## Repository Contract

```php
interface ProjectRepository
{
    public function get(ProjectId $id): Project;
    public function save(Project $project): void;
}
```

---

## Infrastructure Implementation (excerpt)

```php
final class MysqlProjectRepository implements ProjectRepository
{
    public function get(ProjectId $id): Project
    {
        $row = $this->db->fetch('SELECT * FROM projects WHERE id = ?', [$id]);
        return ProjectMapper::fromRow($row);
    }
}
```

---

## Key Rules

- SQL isolated
- Mapper owns translation
- Domain untouched by persistence

---

**Authority**: Reference

