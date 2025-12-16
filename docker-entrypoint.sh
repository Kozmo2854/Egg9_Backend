#!/bin/bash
set -e

# Fix MPM configuration at runtime (Railway re-enables modules after build)
echo "Configuring Apache MPM..."
a2dismod mpm_event 2>/dev/null || true
a2dismod mpm_worker 2>/dev/null || true
a2dismod mpm_prefork 2>/dev/null || true
a2enmod mpm_prefork

echo "Starting Apache on port 8080..."
exec apache2-foreground

