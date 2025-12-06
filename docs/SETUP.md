# PSP4 Laravel Application Setup

## Required Services for Development

This Laravel application requires several background services to function properly. Run these commands in **separate terminal windows** and keep them running.

### 1. Redis Server (Database & Queue Backend)

Redis is required for queues and caching. It's installed at `C:\Redis\` on this system.

```powershell
# Check if Redis is already running
netstat -an | findstr :6379

# Start Redis server (if not running)
Start-Process -FilePath "C:\Redis\redis-server.exe" -WorkingDirectory "C:\Redis" -WindowStyle Minimized

# Or run in foreground to see logs
C:\Redis\redis-server.exe

# Verify Redis is working
php artisan tinker --execute="echo Redis::connection()->ping() ? 'Connected' : 'Failed';"
```

#### Install Redis as Windows Service (Run Once - Administrator Required)

```powershell
# Run PowerShell as Administrator
C:\Redis\redis-server.exe --service-install
C:\Redis\redis-server.exe --service-start

# Service management commands
C:\Redis\redis-server.exe --service-stop
C:\Redis\redis-server.exe --service-start
C:\Redis\redis-server.exe --service-uninstall
```

### 2. Laravel Reverb WebSocket Server

Handles real-time updates for ballot counting.

```powershell
# Terminal Window #1: Start Reverb WebSocket server
php artisan reverb:start

# Alternative with specific options:
php artisan reverb:start --host=0.0.0.0 --port=8080 --hostname=psp4.test
```

### 3. Queue Workers

Processes ballot entries and aggregates results.

```powershell
# Terminal Window #2: Start queue workers
php artisan queue:work redis --queue=ballot-entry,aggregation,imports,default --tries=3 --timeout=60 --sleep=3 --max-jobs=1000 --max-time=3600

# Alternative: Process jobs one at a time (for debugging)
php artisan queue:work --once
```

### 4. Laravel Development Server (Optional)

If not using Laravel Herd, start the development server:

```powershell
# Terminal Window #3: Laravel dev server (only if not using Herd)
php artisan serve --host=0.0.0.0 --port=8000
```

### 5. Frontend Build Process (If Needed)

For frontend asset compilation:

```powershell
# Terminal Window #4: Watch for changes and rebuild assets
npm run dev

# Or build once for production
npm run build
```

## Service Management Commands

### Check Service Status

```powershell
# Check Redis connection and port
netstat -an | findstr :6379

# Test Redis through Laravel
php artisan tinker --execute="echo Redis::connection()->ping() ? 'Redis OK' : 'Redis Failed';"

# Check if queue workers are processing
php artisan queue:failed
php artisan queue:monitor

# Check Reverb status (browser)
# Visit: https://psp4.test/reverb/status
```

### Restart Services

```powershell
# Restart queue workers (graceful)
php artisan queue:restart

# Clear failed jobs
php artisan queue:flush

# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```

### Debugging Commands

```powershell
# Check if Redis is running
netstat -an | findstr :6379

# Test Redis connection through Laravel
php artisan tinker --execute="Redis::connection()->ping();"

# Monitor Redis queues directly through Laravel
php artisan tinker --execute="echo 'ballot-entry: ' . Redis::connection()->llen('queues:ballot-entry') . PHP_EOL; echo 'aggregation: ' . Redis::connection()->llen('queues:aggregation');"

# Monitor Laravel logs
Get-Content storage\logs\laravel.log -Tail 50 -Wait

# Check database connections
php artisan tinker
> DB::connection()->getPdo()
> exit
```

## Production Setup (Windows Service)

For production, convert queue workers to Windows services:

### Using NSSM (Non-Sucking Service Manager)

```powershell
# 1. Install NSSM
choco install nssm

# 2. Create queue worker service
nssm install PSPQueueWorker "C:\path\to\php.exe" "C:\Users\baawa\Herd\psp4\artisan queue:work redis --queue=ballot-entry,aggregation,default --tries=3 --timeout=60 --sleep=3"

# 3. Set working directory
nssm set PSPQueueWorker AppDirectory "C:\Users\baawa\Herd\psp4"

# 4. Start service
nssm start PSPQueueWorker

# 5. Create Reverb service
nssm install PSPReverbServer "C:\path\to\php.exe" "C:\Users\baawa\Herd\psp4\artisan reverb:start"
nssm set PSPReverbServer AppDirectory "C:\Users\baawa\Herd\psp4"
nssm start PSPReverbServer
```

## Troubleshooting

### Queue Workers Not Processing
```powershell
# Check Redis is running
netstat -an | findstr :6379

# If not running, start Redis
Start-Process -FilePath "C:\Redis\redis-server.exe" -WorkingDirectory "C:\Redis" -WindowStyle Minimized

# Restart workers
php artisan queue:restart
php artisan queue:work redis --queue=ballot-entry,aggregation,default --tries=3 --timeout=60
```

### WebSocket Connection Issues
```powershell
# Check Reverb is running on correct port
netstat -an | findstr :8080

# Restart Reverb
php artisan reverb:restart
```

### Database Connection Issues
```powershell
# Test database connection
php artisan migrate:status

# Check environment
php artisan env
```

## Development Workflow

1. **Start all services** (Redis, Reverb, Queue Workers)
2. **Access application**: https://psp4.test
3. **Monitor logs**: Watch `storage/logs/laravel.log`
4. **Test ballot entry**: Login and submit ballots
5. **Verify real-time updates**: Check counts update automatically

## Quick Start Script

Save as `start-services.ps1`:

```powershell
# Start all required services for PSP4 development

Write-Host "Starting PSP4 Development Environment..." -ForegroundColor Green

# Check if Redis is running
$redisRunning = netstat -an | findstr :6379
if (-not $redisRunning) {
    Write-Host "Starting Redis server..." -ForegroundColor Yellow
    Start-Process -FilePath "C:\Redis\redis-server.exe" -WorkingDirectory "C:\Redis" -WindowStyle Minimized
    Start-Sleep 2
}

# Verify Redis connection
try {
    $null = php artisan tinker --execute="Redis::connection()->ping();" 2>&1
    Write-Host "✓ Redis is running" -ForegroundColor Green
} catch {
    Write-Host "✗ Redis not running. Please start Redis first." -ForegroundColor Red
    exit 1
}

# Set working directory
Set-Location "C:\Users\baawa\Herd\psp4"

# Start Reverb in background
Write-Host "Starting Reverb WebSocket server..." -ForegroundColor Yellow
Start-Process powershell -ArgumentList "-Command", "cd C:\Users\baawa\Herd\psp4; php artisan reverb:start" -WindowStyle Minimized

# Wait a moment
Start-Sleep 2

# Start Queue Workers
Write-Host "Starting Queue Workers..." -ForegroundColor Yellow
php artisan queue:work redis --queue=ballot-entry,aggregation,default --tries=3 --timeout=60 --sleep=3 --max-jobs=1000 --max-time=3600
```

Run with: `.\start-services.ps1`