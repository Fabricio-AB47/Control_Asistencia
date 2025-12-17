<#
fix_iis_site.ps1

Purpose: Find the IIS site that serves the host binding "asistencia.intec.edu.ec" and set its physical path to the project's
public folder (C:\xampp\htdocs\Control_Asistencia\control_horario\public). The script will also set NTFS permissions for
IIS and restart the site and its application pool.

Usage (run as Administrator on the Windows server):
    powershell -ExecutionPolicy Bypass -File .\tools\fix_iis_site.ps1

IMPORTANT: run this on the IIS server. The script makes changes to IIS site configuration and NTFS permissions.
#>

param()

Write-Host "Starting fix_iis_site.ps1" -ForegroundColor Cyan

# Target host and new path - change if needed
$targetHost = 'asistencia.intec.edu.ec'
$newPath = 'C:\xampp\htdocs\Control_Asistencia\control_horario\public'

# Load WebAdministration
Try {
    Import-Module WebAdministration -ErrorAction Stop
} Catch {
    Write-Error "Failed to load WebAdministration module. Run this script in PowerShell (Admin) on the IIS server."
    Exit 1
}

# Check newPath exists
if (-not (Test-Path $newPath)) {
    Write-Error "Target path '$newPath' does not exist on this machine. Please ensure the project is deployed there."
    Exit 1
}

# Find site by binding hostname
$sites = Get-ChildItem IIS:\Sites
$match = $null
foreach ($s in $sites) {
    foreach ($b in $s.Bindings) {
        if ($b.hostHeader -and ($b.hostHeader -ieq $targetHost)) {
            $match = $s
            break
        }
    }
    if ($match) { break }
}

# Fallback: if no site has the hostHeader (common when bindings are empty), try a site named 'Asistencia'
if (-not $match) {
    Write-Host "No site found by host binding '$targetHost'. Trying site named 'Asistencia' as fallback..." -ForegroundColor Yellow
    try {
        $fallback = Get-Item IIS:\Sites\Asistencia -ErrorAction Stop
        $match = $fallback
        Write-Host "Using site 'Asistencia' as target." -ForegroundColor Green
    } catch {
        Write-Host "No site named 'Asistencia' found. Available sites and bindings:" -ForegroundColor Yellow
        foreach ($s in $sites) {
            Write-Host "- $($s.Name) -> $($s.Bindings | ForEach-Object { $_.bindingInformation + ' [' + $_.protocol + '] host=' + $_.hostHeader })"
        }
        Write-Host "If your site uses a different host or uses IP-based binding, run the script again and edit the targetHost variable or adjust the fallback name." -ForegroundColor Yellow
        Exit 2
    }
}

Write-Host "Found site: $($match.Name)" -ForegroundColor Green

# Backup current physicalPath
$siteItem = Get-Item IIS:\Sites\$($match.Name)
$currentPath = $siteItem.physicalPath
Write-Host "Current physicalPath: $currentPath"

# Set new physicalPath
Try {
    Set-ItemProperty IIS:\Sites\$($match.Name) -Name physicalPath -Value $newPath -ErrorAction Stop
    Write-Host "Updated physicalPath to: $newPath" -ForegroundColor Green
} Catch {
    Write-Error "Failed to update physicalPath: $_"
    Exit 3
}

# Ensure NTFS permissions for IIS_IUSRS (read & execute)
Try {
    Write-Host "Setting NTFS permissions for IIS_IUSRS on $newPath..."
    icacls $newPath /grant "IIS_IUSRS:(RX)" /T | Out-Null
    Write-Host "Permissions applied." -ForegroundColor Green
} Catch {
    Write-Warning "Failed to apply NTFS permissions automatically. You may need to set them manually."
}

# Detect php-cgi.exe location and write site web.config accordingly
Write-Host "Detecting php-cgi.exe..."
$phpPaths = @(
        'C:\\xampp\\php\\php-cgi.exe',
        'C:\\php\\php-cgi.exe',
        'C:\\Program Files\\PHP\\php-cgi.exe',
        'C:\\Program Files (x86)\\PHP\\php-cgi.exe'
)
$foundPhp = $null
foreach ($p in $phpPaths) {
        if (Test-Path $p) { $foundPhp = $p; break }
}
if (-not $foundPhp) {
        # try a fast search (may take time)
        try {
                $found = Get-ChildItem C:\ -Filter php-cgi.exe -Recurse -ErrorAction SilentlyContinue -Force | Select-Object -First 1
                if ($found) { $foundPhp = $found.FullName }
        } catch {
                # ignore
        }
}

if ($foundPhp) {
        Write-Host "Found php-cgi at: $foundPhp" -ForegroundColor Green
        # create a web.config in the newPath to ensure FastCGI is configured for this site
        $webconfigPath = Join-Path $newPath 'web.config'
        $escaped = $foundPhp -replace '\\', '\\\\'
        $webContent = @"
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <system.webServer>
        <defaultDocument enabled="true">
            <files>
                <add value="index.php" />
            </files>
        </defaultDocument>
        <directoryBrowse enabled="false" />
        <fastCgi>
            <application fullPath="$escaped">
                <environmentVariables>
                    <environmentVariable name="PHPRC" value="$(Split-Path -Parent $escaped)" />
                </environmentVariables>
            </application>
        </fastCgi>
        <handlers>
            <remove name="PHP_via_FastCGI" />
            <add name="PHP_via_FastCGI" path="*.php" verb="*" modules="FastCgiModule" scriptProcessor="$escaped" resourceType="Either" requireAccess="Script" />
        </handlers>
    </system.webServer>
</configuration>
"@
        try {
                Set-Content -Path $webconfigPath -Value $webContent -Encoding UTF8 -Force
                Write-Host "Wrote web.config to $webconfigPath" -ForegroundColor Green
        } catch {
                Write-Warning "Failed to write web.config: $_"
        }
} else {
        Write-Warning "Could not find php-cgi.exe automatically. You may need to install PHP or adjust web.config manually." -ForegroundColor Yellow
}

# Restart site
Try {
    Write-Host "Restarting site '$($match.Name)'..."
    Restart-WebItem "IIS:\Sites\$($match.Name)"
    Write-Host "Site restarted." -ForegroundColor Green
} Catch {
    Write-Warning "Failed to restart site: $_"
}

# Restart the app pool used by the site (if known)
$appPool = $match.applicationPool
if ($appPool) {
    Try {
        Write-Host "Restarting Application Pool: $appPool"
        Restart-WebAppPool $appPool
        Write-Host "AppPool restarted." -ForegroundColor Green
    } Catch {
        Write-Warning "Failed to restart AppPool: $_"
    }
} else {
    Write-Host "No applicationPool found for site (or not accessible). Skipping AppPool restart." -ForegroundColor Yellow
}

# Final verification
Write-Host "Verifying index.php exists under new path..."
if (Test-Path (Join-Path $newPath 'index.php')) {
    Write-Host "index.php found." -ForegroundColor Green
    Write-Host "Open https://$targetHost/ in the browser. If you previously saw 'CSRF inválido', clear cookies for this domain and reload the page." -ForegroundColor Cyan
} else {
    Write-Warning "index.php NOT found under $newPath. Confirm the deployment path and try again."
}

Write-Host "Done." -ForegroundColor Cyan

# Helpful hints for the admin
Write-Host "\nIf you still get a 404: check C:\inetpub\logs\LogFiles\ for the W3SVC log and examine the 404 substatus (e.g., 404.3 = handler/mime issue, 404.0 = not found)." -ForegroundColor Yellow
Write-Host "If PHP isn't executed and you see the PHP source or a download, confirm Handler Mappings -> FastCGI is configured and that php-cgi.exe path is correct (web.config may reference C:\xampp\php\php-cgi.exe)." -ForegroundColor Yellow

Exit 0
