# PET (Plan. Execute. Track)

**PET** is a comprehensive, domain-driven operational platform built as a WordPress plugin. It manages the full lifecycle of professional services: from quoting and contracting to project delivery, support, and financial forecasting.

## 🚀 Vision

To provide a single, immutable source of operational truth that integrates commercial governance, project execution, and people management into a unified event-driven system.

## 📦 Core Modules

### 1. Commercial Engine
*   **Quotes:** Versioned, immutable quoting system with complex component types (Catalog, Implementation, Recurring).
*   **Contracts:** Auto-generated from accepted quotes.
*   **Baselines:** Financial snapshots for variance tracking.
*   **Adjustments:** Strict audit trail for cost/price changes (Write-offs, Credits).

### 2. Delivery Engine
*   **Projects:** Automatically provisioned from accepted quotes.
*   **Milestones:** Payment-linked delivery targets.
*   **Tasks:** Granular work items derived from quote blueprints.

### 3. Support & SLA Engine
*   **Tickets:** State-machine driven helpdesk.
*   **SLA Automation:** Real-time tracking of First Response and Resolution times.
*   **Auto-Breach:** Automated escalation and breach detection via cron.

### 4. People & Capability
*   **Skills Matrix:** Proficiency tracking (1-5 scale) with evidence requirements.
*   **Certifications:** Expiry tracking and verification.
*   **Capacity:** Resource availability and utilization planning.

### 5. Financial Intelligence
*   **Forecasts:** Probability-weighted revenue projection.
*   **Revenue Recognition:** Accrual-based tracking via milestones.

## 🏗 Architecture

PET follows **Domain-Driven Design (DDD)** and **Clean Architecture** principles:

*   **Domain Layer:** Pure PHP entities, value objects, invariants, and events. No framework dependencies.
*   **Application Layer:** Use cases, command handlers, and service orchestration.
*   **Infrastructure Layer:** Repositories (WordPress DB), external integrations, and persistence logic.
*   **UI Layer:** 
    *   **Admin:** React-based Single Page Application (SPA) embedded in WordPress Admin.
    *   **API:** RESTful endpoints for frontend consumption.

### Event-Driven
All state changes emit immutable domain events (e.g., `quote.accepted`, `ticket.breached`, `delivery.milestone_completed`). These events drive projections, notifications, and side effects, ensuring decoupling and auditability.

## 🛠 Installation & Setup

### Prerequisites
*   PHP 8.1+
*   WordPress 6.0+
*   Node.js 18+
*   Composer

### Setup Steps
1.  **Clone the repository:**
    ```bash
    git clone https://github.com/stevecopeza/pet.git
    cd pet
    ```

2.  **Install Backend Dependencies:**
    ```bash
    composer install
    ```

3.  **Install Frontend Dependencies:**
    ```bash
    npm install
    ```

4.  **Build Frontend Assets:**
    ```bash
    npm run build
    ```

5.  **Activate Plugin:**
    Activate **PET (Plan. Execute. Track)** via the WordPress Plugins screen.

## ✅ System Readiness

PET includes a **Demo Pre-Flight Check** system to validate environment health before activation.

**Check Endpoint:**
`GET /wp-json/pet/v1/system/pre-demo-check`

**Checks Performed:**
*   **SLA Automation:** Verifies `sla_clock_state` table and service availability.
*   **Event Registry:** Confirms all critical events are wired.
*   **Quote Validation:** Enforces schema invariants (SKU, Role IDs).

## 🧪 Testing

### Unit & Integration (PHPUnit)
```bash
./vendor/bin/phpunit
```

### End-to-End (Playwright)
```bash
npx playwright test
```

## 📚 Documentation

Extensive architectural and functional documentation is available in the `docs/` directory:
*   [Architecture](docs/01_architecture/)
*   [Domain Model](docs/02_domain_model/)
*   [Data Model](docs/05_data_model/)
*   [API Contract](docs/19_api_contract/)
*   [Event Registry](docs/20_event_registry/)

---
*Built with ❤️ by Trae for Steve Cope.*
