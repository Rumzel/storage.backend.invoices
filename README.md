## Storage Invoicing Module

### What is this module

This is a backend module responsible for **generating storage invoices** for:

* **Storage owners** — who provide warehouse space
* **Marketplaces** — which use these storages and pay for item storage

### Purpose / Problem it solves

The goal of this module is to automate **billing for item storage** over a given time period:

* calculate actual storage time per item (in hours)
* apply storage tariffs (standard / oversize, peak / off-peak)
* generate invoices and invoice line items
* produce PDF invoices and send them to marketplaces

---

### How it works (high level)

1. A date range is provided (`dateFrom` / `dateTill`)
2. The system iterates over storages or marketplaces
3. For each item piece:

    * calculates storage hours within the period
    * calculates cost using tariff rules
4. Invoice entities and invoice item records are persisted
5. A PDF invoice is generated (HTML → PDF)
6. Marketplace invoices can be emailed automatically

---

### Key responsibilities

* **Invoice generation** (idempotent per period)
* **Storage cost calculation** (tariff & season based)
* **PDF generation** (wkhtmltopdf via KnpSnappy)
* **Email delivery** for marketplace invoices

---

### Why this exists

This module provides a **clear and repeatable billing flow** for warehouse storage usage, separating
business logic (tariffs, periods, storage time) from presentation (PDF, email) and persistence (Doctrine).