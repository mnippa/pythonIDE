# API Test Suite for pythonIDE
# Simplified version with proper PowerShell syntax

$baseUrl = "http://localhost/pythonIDE"
$testResults = @()
$passCount = 0
$failCount = 0

function Test-API {
    param(
        [string]$Name,
        [string]$Endpoint,
        [string]$Method = "GET",
        [hashtable]$Body = @{},
        [Microsoft.PowerShell.Commands.WebRequestSession]$Session = $null,
        [bool]$ExpectFail = $false
    )
    
    $uri = "$baseUrl/$Endpoint"
    
    try {
        $params = @{
            Uri = $uri
            Method = $Method
            ContentType = "application/json"
            UseBasicParsing = $true
            ErrorAction = "Stop"
        }
        
        if ($Session) { $params.WebSession = $Session }
        if ($Method -ne "GET" -and $Body.Count -gt 0) {
            $params.Body = ($Body | ConvertTo-Json -Depth 10)
        }
        
        $response = Invoke-WebRequest @params
        $data = $null
        try { $data = $response.Content | ConvertFrom-Json } catch {}
        
        if ($ExpectFail) {
            $script:failCount++
            Write-Host "[FAIL] $Name - Should have failed but succeeded" -ForegroundColor Red
            return @{ Success = $false; Session = $Session }
        } else {
            $script:passCount++
            Write-Host "[PASS] $Name" -ForegroundColor Green
            return @{ Success = $true; Data = $data; Session = $Session; StatusCode = $response.StatusCode }
        }
        
    } catch {
        if ($ExpectFail) {
            $script:passCount++
            Write-Host "[PASS] $Name - Correctly failed" -ForegroundColor Green
            return @{ Success = $true; Session = $Session }
        } else {
            $script:failCount++
            Write-Host "[FAIL] $Name - Error: $($_.Exception.Message)" -ForegroundColor Red
            return @{ Success = $false; Error = $_.Exception.Message; Session = $Session }
        }
    }
}

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "API TEST SUITE - pythonIDE" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

# Test Data
$timestamp = Get-Date -Format "yyyyMMddHHmmss"
$testUser = @{
    email = "test_${timestamp}@example.com"
    password = "TestPass123"
    first_name = "Test"
    last_name = "User"
}

# ========================================
# AUTHENTICATION TESTS
# ========================================
Write-Host "`n--- AUTHENTICATION TESTS ---`n" -ForegroundColor Yellow

# Test 1: Register new user
$result = Test-API -Name "User Registration" -Endpoint "api/auth/register.php" -Method "POST" -Body $testUser
$userId = if ($result.Data) { $result.Data.user_id } else { $null }

# Test 2: Register duplicate (should fail)
$result = Test-API -Name "Duplicate Registration (expect fail)" -Endpoint "api/auth/register.php" -Method "POST" -Body $testUser -ExpectFail $true

# Test 3: SQL Injection in registration (email with SQL)
$sqlUser = @{ email = "'; DROP TABLE users; --@test.com"; password = "test123"; first_name = "Admin"; last_name = "Test" }
$result = Test-API -Name "SQL Injection Protection (Register)" -Endpoint "api/auth/register.php" -Method "POST" -Body $sqlUser -ExpectFail $true

# Test 4: Login with valid credentials
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$loginData = @{ email = $testUser.email; password = $testUser.password }
$result = Test-API -Name "User Login (valid)" -Endpoint "api/auth/login.php" -Method "POST" -Body $loginData -Session $session
$userSession = $result.Session

# Test 5: Login with invalid password
$invalidLogin = @{ email = $testUser.email; password = "WrongPass" }
$result = Test-API -Name "User Login (invalid password)" -Endpoint "api/auth/login.php" -Method "POST" -Body $invalidLogin -ExpectFail $true

# Test 6: SQL Injection in login
$sqlLogin = @{ email = "admin' OR '1'='1' --@test.com"; password = "anything" }
$result = Test-API -Name "SQL Injection Protection (Login)" -Endpoint "api/auth/login.php" -Method "POST" -Body $sqlLogin -ExpectFail $true

# ========================================
# TASK MANAGEMENT TESTS
# ========================================
Write-Host "`n--- TASK MANAGEMENT TESTS ---`n" -ForegroundColor Yellow

# Test 7: List tasks requires assignment_id - test with invalid ID
$result = Test-API -Name "List Tasks (authenticated, requires assignment_id)" -Endpoint "api/tasks/list.php?assignment_id=99999" -Method "GET" -Session $userSession -ExpectFail $true

# Test 8: List tasks (unauthenticated - should fail)
$result = Test-API -Name "List Tasks (unauthenticated)" -Endpoint "api/tasks/list.php?assignment_id=1" -Method "GET" -ExpectFail $true

Write-Host "[NOTE] Task endpoint tests are limited - require valid assignment fixtures" -ForegroundColor Gray

# ========================================
# FILE OPERATIONS TESTS
# ========================================
Write-Host "`n--- FILE OPERATIONS TESTS ---`n" -ForegroundColor Yellow

# Test 9: Path traversal protection
$pathTraversal = @{ path = "../../../etc/passwd"; content = "test" }
$result = Test-API -Name "Path Traversal Protection" -Endpoint "api/files/read.php" -Method "POST" -Body $pathTraversal -Session $userSession -ExpectFail $true

# Test 10: Malicious filename
$malFile = @{ path = "../../../malicious.php"; content = "test" }
$result = Test-API -Name "Malicious Filename Protection" -Endpoint "api/files/create.php" -Method "POST" -Body $malFile -Session $userSession -ExpectFail $true

# ========================================
# INPUT VALIDATION TESTS
# ========================================
Write-Host "`n--- INPUT VALIDATION TESTS ---`n" -ForegroundColor Yellow

# Test 11: Missing required fields
$incomplete = @{ email = "testonly@test.com" }
$result = Test-API -Name "Missing Required Fields" -Endpoint "api/auth/register.php" -Method "POST" -Body $incomplete -ExpectFail $true

# Test 12: Large input (DoS protection)
$largeInput = @{ name = "A" * 100000; email = "test@test.com"; password = "test" }
$result = Test-API -Name "Large Input Handling" -Endpoint "api/auth/register.php" -Method "POST" -Body $largeInput -ExpectFail $true

# ========================================
# SESSION MANAGEMENT TESTS
# ========================================
Write-Host "`n--- SESSION MANAGEMENT TESTS ---`n" -ForegroundColor Yellow

# Test 12: Session persistence - test with a simple endpoint
$result1 = Test-API -Name "Session Persistence (Request 1)" -Endpoint "api/auth/login.php" -Method "POST" -Body $loginData -Session $userSession
$result2 = Test-API -Name "Session Persistence (Request 2)" -Endpoint "api/auth/login.php" -Method "POST" -Body $loginData -Session $userSession

# Test 13: Logout
$result = Test-API -Name "Logout" -Endpoint "api/auth/logout.php" -Method "POST" -Session $userSession

# Test 14: Access after logout (should fail)
$result = Test-API -Name "Access After Logout (expect fail)" -Endpoint "api/tasks/list.php?assignment_id=1" -Method "GET" -Session $userSession -ExpectFail $true

# ========================================
# ERROR HANDLING TESTS
# ========================================
Write-Host "`n--- ERROR HANDLING TESTS ---`n" -ForegroundColor Yellow

# Test 17: Non-existent endpoint
try {
    $response = Invoke-WebRequest -Uri "$baseUrl/api/nonexistent/test.php" -Method GET -UseBasicParsing -ErrorAction Stop
    $script:failCount++
    Write-Host "[FAIL] Non-Existent Endpoint - Should return 404" -ForegroundColor Red
} catch {
    if ($_.Exception.Response.StatusCode.Value__ -eq 404) {
        $script:passCount++
        Write-Host "[PASS] Non-Existent Endpoint - Correctly returns 404" -ForegroundColor Green
    } else {
        $script:failCount++
        Write-Host "[FAIL] Non-Existent Endpoint - Wrong status code" -ForegroundColor Red
    }
}

# ========================================
# FINAL REPORT
# ========================================
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "TEST RESULTS" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Total Tests: $($passCount + $failCount)" -ForegroundColor White
Write-Host "Passed: $passCount" -ForegroundColor Green
Write-Host "Failed: $failCount" -ForegroundColor Red
$successRate = if (($passCount + $failCount) -gt 0) { [math]::Round(($passCount / ($passCount + $failCount)) * 100, 2) } else { 0 }
Write-Host "Success Rate: $successRate%" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

if ($failCount -eq 0) {
    Write-Host "ALL TESTS PASSED!" -ForegroundColor Green
} else {
    Write-Host "SOME TESTS FAILED - Review output above" -ForegroundColor Yellow
}

# Return summary
return @{
    Total = $passCount + $failCount
    Passed = $passCount
    Failed = $failCount
    SuccessRate = $successRate
}
