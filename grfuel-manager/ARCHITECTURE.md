# GRFuel Manager 2.0

**Forms that become workflows.**

## Alpha foundation

Η έκδοση 2.0.0-alpha.1 αποτελεί ασφαλή βάση μετάβασης από τη σειρά Secure Form Vault 1.x.

### Compatibility retained

- Custom post type: `sfv_form`
- Submission table: `{prefix}sfv_submissions`
- Settings option: `sfv_settings`
- Existing metadata and form schemas
- Legacy shortcodes: `[secure_contact_form]`, `[sfv_price]`

### New public aliases

- `[beyond_form id="123"]`
- `[beyond_price form="123" product="Product"]`

Τα εσωτερικά identifiers θα μετακινηθούν μόνο μέσω versioned migrations, ώστε να μην υπάρξει απώλεια δεδομένων.

## Planned module boundaries

- Core and migrations
- Field registry and shared field capabilities
- Builder canvas and inspector
- Rendering and validation
- Submissions and privacy tools
- Workflow actions
- Pricing and calculations
- PDF, XLSX and XML exports
- YOOtheme integration
