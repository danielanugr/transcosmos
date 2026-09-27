#!/usr/bin/env bash
set -e

echo "Running Full Local CI/CD Pipeline Verification"

echo ""
echo "[1/4] Running Backend Tests (PHPUnit)..."
cd backend
php artisan test
cd ..

echo ""
echo "[2/4] Running Frontend Tests (Vitest)..."
cd frontend
npm test

echo ""
echo "[3/4] Verifying Next.js Production Build..."
npm run build
cd ..

echo ""
echo "[SUCCESS] All CI/CD Checks Passed Successfully!"
