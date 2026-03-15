# ====================================================================
# UI Smoke Test Suite - pythonIDE
# ====================================================================
# Tests: Page accessibility, redirects, titles, role-based access
# ====================================================================

$baseUrl = "http://localhost/pythonIDE/public"
$apiUrl = "http://localhost/pythonIDE/api"
$testResults = @()
$passCount = 0
$failCount = 0

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "UI SMOKE TEST SUITE - pythonIDE" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

# ====================================================================
# Helper Functions
# ====================================================================

function Test-PageAccess {
    param(
        [string]$Name,
        [string]$Page,
        [int]$ExpectedStatus,
        [string]$ExpectedRedirect = "",
        [string]$ExpectedTitle = "",
        [Microsoft.PowerShell.Commands.WebRequestSession]$Session = $null,
        [int[]]$AlternativeStatuses = @()
    )
    
    $url = "$baseUrl/$Page"
    
    try {
        $req = [System.Net.HttpWebRequest]::Create($url)
        $req.Method = 'GET'
        $req.AllowAutoRedirect = $false
        $req.UserAgent = 'PowerShell-UITest/1.0'
        
        # Add session cookies if provided
        if ($Session -and $Session.Cookies) {
            $req.CookieContainer = $Session.Cookies
        }
        
        $resp = $req.GetResponse()
        $statusCode = [int]$resp.StatusCode
        $location = $resp.Headers['Location']
        
        # Read content for title check
        $stream = $resp.GetResponseStream()
        $reader = New-Object System.IO.StreamReader($stream)
        $content = $reader.ReadToEnd()
        $reader.Close()
        $stream.Close()
        $resp.Close()
        
        # Extract title
        $titleMatch = [regex]::Match($content, '<title>(.*?)</title>', 'IgnoreCase')
        $actualTitle = if ($titleMatch.Success) { $titleMatch.Groups[1].Value } else { "" }
        
        # Validate expectations (allow alternative status codes)
        $statusMatch = ($statusCode -eq $ExpectedStatus) -or ($AlternativeStatuses -contains $statusCode)
        $redirectMatch = if ($ExpectedRedirect) { $location -like "*$ExpectedRedirect*" } else { $true }
        $titleMatch = if ($ExpectedTitle) { $actualTitle -like "*$ExpectedTitle*" } else { $true }
        
        $passed = $statusMatch -and $redirectMatch -and $titleMatch
        
        if ($passed) {
            $script:passCount++
            Write-Host "[PASS] $Name" -ForegroundColor Green
            if ($location) { Write-Host "       Redirect: $location" -ForegroundColor Gray }
            if ($actualTitle) { Write-Host "       Title: $actualTitle" -ForegroundColor Gray }
        } else {
            $script:failCount++
            Write-Host "[FAIL] $Name" -ForegroundColor Red
            if (-not $statusMatch) { Write-Host "       Expected status: $ExpectedStatus, Got: $statusCode" -ForegroundColor Yellow }
            if (-not $redirectMatch) { Write-Host "       Expected redirect: $ExpectedRedirect, Got: $location" -ForegroundColor Yellow }
            if (-not $titleMatch) { Write-Host "       Expected title pattern: $ExpectedTitle, Got: $actualTitle" -ForegroundColor Yellow }
        }
        
        return @{
            Passed = $passed
            StatusCode = $statusCode
            Location = $location
            Title = $actualTitle
        }
        
    } catch [System.Net.WebException] {
        # Handle HTTP errors like 403, 404, etc.
        $httpResp = $_.Exception.Response
        if ($httpResp) {
            $statusCode = [int]$httpResp.StatusCode
            $statusMatch = ($statusCode -eq $ExpectedStatus) -or ($AlternativeStatuses -contains $statusCode)
            
            if ($statusMatch) {
                $script:passCount++
                Write-Host "[PASS] $Name (HTTP $statusCode)" -ForegroundColor Green
                return @{ Passed = $true; StatusCode = $statusCode }
            } else {
                $script:failCount++
                Write-Host "[FAIL] $Name - Expected: $ExpectedStatus, Got: $statusCode" -ForegroundColor Red
                return @{ Passed = $false; StatusCode = $statusCode }
            }
        } else {
            $script:failCount++
            Write-Host "[FAIL] $Name - Exception: $($_.Exception.Message)" -ForegroundColor Red
            return @{ Passed = $false; Error = $_.Exception.Message }
        }
    } catch {
        $script:failCount++
        Write-Host "[FAIL] $Name - Exception: $($_.Exception.Message)" -ForegroundColor Red
        return @{ Passed = $false; Error = $_.Exception.Message }
    }
}

# ====================================================================
# TEST SUITE 1: Guest Access
# ====================================================================

Write-Host "`n--- GUEST ACCESS TESTS ---`n" -ForegroundColor Yellow

# Public pages should load with 200
Test-PageAccess -Name "Guest: index.php (Landing)" -Page "index.php" -ExpectedStatus 200 -ExpectedTitle "Python IDE"
Test-PageAccess -Name "Guest: login.php" -Page "login.php" -ExpectedStatus 200 -ExpectedTitle "Login"
Test-PageAccess -Name "Guest: register.php" -Page "register.php" -ExpectedStatus 200
Test-PageAccess -Name "Guest: free.php" -Page "free.php" -ExpectedStatus 200 -ExpectedTitle "Python IDE"
Test-PageAccess -Name "Guest: share.php" -Page "share.php" -ExpectedStatus 200

# Protected pages should redirect to login
Test-PageAccess -Name "Guest: dashboard.php (redirect)" -Page "dashboard.php" -ExpectedStatus 302 -ExpectedRedirect "login.php"
Test-PageAccess -Name "Guest: assignments.php (redirect)" -Page "assignments.php" -ExpectedStatus 302 -ExpectedRedirect "login.php"
Test-PageAccess -Name "Guest: projects.php (redirect)" -Page "projects.php" -ExpectedStatus 302 -ExpectedRedirect "login.php"
Test-PageAccess -Name "Guest: admin.php (redirect)" -Page "admin.php" -ExpectedStatus 302 -ExpectedRedirect "login.php"
Test-PageAccess -Name "Guest: evaluation.php (redirect)" -Page "evaluation.php" -ExpectedStatus 302 -ExpectedRedirect "login.php"
Test-PageAccess -Name "Guest: editor.php (redirect)" -Page "editor.php" -ExpectedStatus 302 -ExpectedRedirect "projects.php"

# ====================================================================
# TEST SUITE 2: Authenticated User Access
# ====================================================================

Write-Host "`n--- AUTHENTICATED USER TESTS ---`n" -ForegroundColor Yellow

# Create test user and login
$timestamp = Get-Date -Format "yyyyMMddHHmmss"
$testUser = @{
    email = "uismoke_${timestamp}@example.com"
    password = "TestPass123"
    first_name = "Smoke"
    last_name = "Test"
}

try {
    # Register
    $registerBody = $testUser | ConvertTo-Json
    $registerResp = Invoke-WebRequest -Uri "$apiUrl/auth/register.php" -Method POST -ContentType "application/json" -Body $registerBody -UseBasicParsing -ErrorAction Stop
    
    # Login and get session
    $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $loginBody = @{
        email = $testUser.email
        password = $testUser.password
    } | ConvertTo-Json
    $loginResp = Invoke-WebRequest -Uri "$apiUrl/auth/login.php" -Method POST -ContentType "application/json" -Body $loginBody -WebSession $session -UseBasicParsing -ErrorAction Stop
    
    Write-Host "Test user created and logged in successfully" -ForegroundColor Gray
    
    # User should be able to access main pages
    Test-PageAccess -Name "User: dashboard.php" -Page "dashboard.php" -ExpectedStatus 200 -ExpectedTitle "Dashboard" -Session $session
    Test-PageAccess -Name "User: assignments.php" -Page "assignments.php" -ExpectedStatus 200 -ExpectedTitle "Aufgaben" -Session $session
    Test-PageAccess -Name "User: projects.php" -Page "projects.php" -ExpectedStatus 200 -ExpectedTitle "Projekte" -Session $session
    
    # Admin pages should return 403 Forbidden or 302 redirect for normal user
    Test-PageAccess -Name "User: admin.php (access denied)" -Page "admin.php" -ExpectedStatus 403 -AlternativeStatuses @(302) -Session $session
    Test-PageAccess -Name "User: evaluation.php (access denied)" -Page "evaluation.php" -ExpectedStatus 403 -AlternativeStatuses @(302) -Session $session
    
    # Editor redirects to projects (requires project_id)
    Test-PageAccess -Name "User: editor.php (redirect)" -Page "editor.php" -ExpectedStatus 302 -ExpectedRedirect "projects.php" -Session $session
    
} catch {
    Write-Host "[ERROR] Could not create test user or login: $($_.Exception.Message)" -ForegroundColor Red
    $script:failCount++
}

# ====================================================================
# TEST SUITE 3: Content Integrity
# ====================================================================

Write-Host "`n--- CONTENT INTEGRITY TESTS ---`n" -ForegroundColor Yellow

# Check for expected UI elements in key pages
function Test-ContentMarker {
    param(
        [string]$Name,
        [string]$Url,
        [string]$ExpectedMarker,
        [Microsoft.PowerShell.Commands.WebRequestSession]$Session = $null
    )
    
    try {
        $params = @{
            Uri = $Url
            Method = 'GET'
            UseBasicParsing = $true
        }
        if ($Session) { $params.WebSession = $Session }
        
        $response = Invoke-WebRequest @params
        $content = $response.Content
        
        if ($content -like "*$ExpectedMarker*") {
            $script:passCount++
            Write-Host "[PASS] $Name - Marker found" -ForegroundColor Green
        } else {
            $script:failCount++
            Write-Host "[FAIL] $Name - Marker '$ExpectedMarker' not found" -ForegroundColor Red
        }
    } catch {
        $script:failCount++
        Write-Host "[FAIL] $Name - Error: $($_.Exception.Message)" -ForegroundColor Red
    }
}

Test-ContentMarker -Name "Content: login.php has email input" -Url "$baseUrl/login.php" -ExpectedMarker "id='email'"
Test-ContentMarker -Name "Content: login.php has password input" -Url "$baseUrl/login.php" -ExpectedMarker "id='password'"
Test-ContentMarker -Name "Content: index.php has navigation" -Url "$baseUrl/index.php" -ExpectedMarker 'login'

if ($session) {
    Test-ContentMarker -Name "Content: dashboard.php has user interface" -Url "$baseUrl/dashboard.php" -ExpectedMarker 'dashboard' -Session $session
    Test-ContentMarker -Name "Content: projects.php has project list" -Url "$baseUrl/projects.php" -ExpectedMarker 'project' -Session $session
}

# ====================================================================
# FINAL REPORT
# ====================================================================

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "SMOKE TEST RESULTS" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Total Tests: $($passCount + $failCount)" -ForegroundColor White
Write-Host "Passed: $passCount" -ForegroundColor Green
Write-Host "Failed: $failCount" -ForegroundColor Red

$successRate = if (($passCount + $failCount) -gt 0) { 
    [math]::Round(($passCount / ($passCount + $failCount)) * 100, 2) 
} else { 
    0 
}
Write-Host "Success Rate: $successRate%" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

if ($failCount -eq 0) {
    Write-Host "ALL SMOKE TESTS PASSED - UI is healthy!" -ForegroundColor Green
    exit 0
} else {
    Write-Host "SOME TESTS FAILED - Review output above" -ForegroundColor Yellow
    exit 1
}
