-- Adds the per-website "only registered landing pages" switch (existing installs). Duplicate-column errors are ignored by the migrators.
ALTER TABLE websites ADD COLUMN strict_pages TINYINT(1) NOT NULL DEFAULT 0 AFTER status;
