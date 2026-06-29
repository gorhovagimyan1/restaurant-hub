# Architecture Decisions

This document records important technical and business decisions made during the project.

---

## ADR-001: Guest Ordering

**Status:** Accepted

### Decision

Customers can place orders without creating an account.

### Reason

The primary goal is to provide the fastest possible ordering experience.

Customers only need to:

1. Scan the QR code.
2. Browse the menu.
3. Add products to the cart.
4. Place the order.

No registration or login is required.

### Future

Customer accounts will become optional in Version 2 to support:

- Loyalty points
- Favorite products
- Order history
- Faster reordering