#!/bin/bash
set -e

echo "Configuring Apache MPM..."

# Disable all MPMs first
a2dismod mpm_event 2>/dev/null || true
a2dismod mpm_worker 2>/dev/null || true
a2dismod mpm_prefork 2>/dev/null || true

# Enable only prefork
a2enmod mpm_prefork

echo "MPM configuration complete. Starting Apache..."

# Start Apache
exec apache2-foreground

