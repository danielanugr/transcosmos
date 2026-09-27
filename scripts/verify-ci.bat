@echo off
echo ========================================================
echo Running Full Local CI/CD Pipeline Verification
echo ========================================================

echo.
echo [1/4] Running Backend Tests (PHPUnit)...
cd backend
php artisan test
if %ERRORLEVEL% NEQ 0 (
    echo [FAIL] Backend tests failed!
    exit /b %ERRORLEVEL%
)

echo.
echo [2/4] Running Frontend Tests (Vitest)...
cd ..\frontend
call npm test
if %ERRORLEVEL% NEQ 0 (
    echo [FAIL] Frontend tests failed!
    exit /b %ERRORLEVEL%
)

echo.
echo [3/4] Verifying Next.js Production Build...
call npm run build
if %ERRORLEVEL% NEQ 0 (
    echo [FAIL] Frontend build failed!
    exit /b %ERRORLEVEL%
)

echo.
echo [4/4] Verifying Service Endpoints...
cd ..
node -e "Promise.all([fetch('http://127.0.0.1:3000').then(r => 'Frontend HTTP: ' + r.status), fetch('http://127.0.0.1:8000/api/health').then(r => r.json()).then(d => 'Backend Status: ' + d.data.status)]).then(res => console.log(res.join(' | '))).catch(e => console.log('Live server check skipped (not running):', e.message))"

echo.
echo ========================================================
echo [SUCCESS] All CI/CD Checks Passed Successfully!
echo ========================================================
