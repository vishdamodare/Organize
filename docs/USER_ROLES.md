# User Roles

| Role | How it's implemented |
|---|---|
| **Company Owner / Admin** | EspoCRM *admin* user of the company's own instance, with `restrictedMode` on |
| **Salesperson** | Regular user with the **Salesperson** role (created by `scripts/setup-company.php`) |
| **Platform Administrator** | The SaaS operator. Has **server** access, **no login** in company workspaces by default |

## Permission matrix (enforced on the server by EspoCRM ACL + module checks)

| Area | Owner | Salesperson |
|---|---|---|
| Customers | all | create · read all · edit **own** · no delete |
| Enquiries | all | create · read all · edit own · no delete · convert own to deal |
| Deals / pipeline | all | create · read all · edit own · no delete |
| Tasks, calls, meetings | all | create · read/edit/delete **own** |
| Products | all | read only |
| Quotations (+ lines) | all | create · read all · edit own (lines follow the quotation) |
| Orders (+ lines) | all, convert quotations | read only, cannot convert |
| Reports page | yes | **no** (dashboard figures still shown, scoped to what they can read) |
| Import / export / mass update | yes | **no** |
| Users, roles, settings | yes | no |
| Install extensions / upgrade | **no** (restricted mode) | no |

"Own" = the record's *Assigned User*. New records default to their creator.

## Platform administrator & support access

- Has no account in company workspaces. Support access is an explicit, logged step: `cd instances/<slug> && php command.php create-admin-user` (or a temporary user created by the owner), removed afterwards. Record who/when/why in your support ticket.
- With server/DB access the operator *technically* can read company data; this is a procedural control, not a technical one (see SECURITY.md).
- Super-admin (`isSuperAdmin`) can be set on a user by the operator through the database if restricted-mode settings must be changed in-app; not needed for normal operation.

## Changing permissions

Owner → Administration (user menu ⋮ → Administration, or `#Admin`) → Roles → Salesperson. Re-running `scripts/setup-company.php` resets the Salesperson role to the defaults above.
