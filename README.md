# IMCB F-10/4 Portal — Phase 11

Phase 11 adds document verification and official student document generation workflows.

## New features
- Student document upload (PDF/JPG/PNG, max 5 MB)
- Admin document verification: pending / verified / rejected + remarks
- Secure authenticated document download
- Student ID card generation and printable layout
- Admin ID-card issue/renew workflow
- Bonafide, Character and Enrollment certificate generation
- Printable certificates / Save as PDF through browser print
- Student dashboard links for documents and ID card

## Setup
1. Extract the project into XAMPP `htdocs` (e.g. `htdocs/imcb_portal`).
2. Create/import the database using `database.sql` in phpMyAdmin.
3. Confirm `config/database.php` credentials.
4. Ensure `uploads/student_docs` is writable by PHP.
5. Run `reset_demo_passwords.php` once if demo passwords need resetting.
6. Open `http://localhost/imcb_portal/`.

## Demo accounts
- Admin: admin@imcb.edu.pk / Admin@123
- Teacher: teacher@imcb.edu.pk / Teacher@123
- Student: student@imcb.edu.pk / Student@123

## Production notes
- Replace demo credentials and database credentials.
- Use HTTPS and server-side CSRF protection before public deployment.
- Validate document retention/privacy rules and official certificate/ID wording with IMCB administration.
- Replace placeholder photo area on the ID card with an institution-approved photo workflow.

## QC / Security Fixes (Phase 11 Final)
- Added server-side CSRF protection to state-changing POST forms.
- Changed destructive admin/student actions from GET links to POST forms.
- Restricted fee receipt access so students can only view their own challans; teachers are denied.
- Added server-side MIME validation with generated safe file extensions for student uploads.
- Added an upload-directory `.htaccess` rule to prevent script execution in uploaded documents.
- Marking fee challans paid is now a POST action with CSRF protection.
- Student ID cards are issued by administration rather than being silently created by a student view.
