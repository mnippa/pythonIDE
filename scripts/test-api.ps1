# ====================================================================
# API Test Suite for pythonIDE - Comprehensive Automated Testing
# ====================================================================
# Tests: Authentication, Task Management, File Operations, Security
# ====================================================================

$baseUrl = "http://localhost/pythonIDE"
$testResults = @()
$testCounter = 0
$passedTests = 0
$failedTests = 0

# ANSI Color Codes for readable output
$GREEN = "`e[32m"
$RED = "`e[31m"
$YELLOW = "`e[33m"
$BLUE = "`e[34m"
$RESET = "`e[0m"

# ====================================================================
# Helper Functions
# ====================================================================

function Write-TestHeader {
    param([string]$Title)
    Write-Host "`n$BLUE========================================$RESET" -ForegroundColor Blue
    Write-Host "$BLUE $Title$RESET" -ForegroundColor Blue
    Write-Host "$BLUE========================================$RESET" -ForegroundColor Blue
}

function Write-TestResult {
    param(
        [string]$TestName,
        [bool]$Passed,
        [string]$Details = "",
        [string]$Expected = "",
        [string]$Actual = ""
    )
    
    $script:testCounter++
    
    $result = @{
        Number = $script:testCounter
        Test = $TestName
        Passed = $Passed
        Details = $Details
        Expected = $Expected
        Actual = $Actual
        Timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    }
    
    $script:testResults += $result
    
    if ($Passed) {
        $script:passedTests++
        Write-Host "${GREEN}✓ TEST $script:testCounter PASSED:$RESET $TestName" -ForegroundColor Green
        if ($Details) { Write-Host "  └─ $Details" -ForegroundColor Gray }
    } else {
        $script:failedTests++
        Write-Host "${RED}✗ TEST $script:testCounter FAILED:$RESET $TestName" -ForegroundColor Red
        if ($Details) { Write-Host "  └─ $Details" -ForegroundColor Gray }
        if ($Expected) { Write-Host "  └─ Expected: $Expected" -ForegroundColor Yellow }
        if ($Actual) { Write-Host "  └─ Actual: $Actual" -ForegroundColor Yellow }
    }
}

function Invoke-ApiRequest {
    param(
        [string]$Endpoint,
        [string]$Method = "GET",
        [hashtable]$Body = @{},
        [Microsoft.PowerShell.Commands.WebRequestSession]$Session = $null,
        [bool]$ExpectError = $false
    )
    
    $uri = "$baseUrl/$Endpoint"
    
    try {
        $params = @{
            Uri = $uri
            Method = $Method
            ContentType = "application/json"
            UseBasicParsing = $true
        }
        
        if ($Session) {
            $params.WebSession = $Session
        }
        
        if ($Method -ne "GET" -and $Body.Count -gt 0) {
            $params.Body = ($Body | ConvertTo-Json -Depth 10)
        }
        
        $response = Invoke-WebRequest @params
        
        $result = @{
            Success = $true
            StatusCode = $response.StatusCode
            Content = $response.Content
            Session = $Session
        }
        
        try {
            $result.Data = $response.Content | ConvertFrom-Json
        } catch {
            $result.Data = $null
        }
        
        return $result
        
    } catch {
        if ($ExpectError) {
            return @{
                Success = $false
                StatusCode = $_.Exception.Response.StatusCode.Value__
                Content = $_.Exception.Response
                Error = $_.Exception.Message
            }
        }
        
        return @{
            Success = $false
            StatusCode = if ($_.Exception.Response) { $_.Exception.Response.StatusCode.Value__ } else { 0 }
            Error = $_.Exception.Message
            Content = ""
        }
    }
}

# ====================================================================
# Test Data
# ====================================================================

$timestamp = Get-Date -Format "yyyyMMddHHmmss"
$testUser = @{
    username = "testuser_$timestamp"
    email = "test_${timestamp}@example.com"
    password = "TestPass123!@#"
}

$testAdmin = @{
    username = "admin"
    password = "admin123"
}

# ====================================================================
# TEST SUITE 1: Authentication & Authorization
# ====================================================================

Write-TestHeader "TEST SUITE 1: Authentication and Authorization"

# Test 1.1: User Registration - Valid Data
Write-Host "`nTest 1.1: User Registration with Valid Data"
$response = Invoke-ApiRequest -Endpoint "api/auth/register.php" -Method "POST" -Body $testUser

if ($response.Success -and $response.Data.success) {
    Write-TestResult -TestName "User Registration (Valid)" -Passed $true -Details "User registered successfully"
    $registeredUserId = $response.Data.user_id
} else {
    Write-TestResult -TestName "User Registration (Valid)" -Passed $false -Details "Registration failed" -Expected "Success response" -Actual $response.Error
}

# Test 1.2: User Registration - Duplicate Username
Write-Host "`nTest 1.2: User Registration with Duplicate Username (Should Fail)"
$response = Invoke-ApiRequest -Endpoint "api/auth/register.php" -Method "POST" -Body $testUser -ExpectError $true

if (-not $response.Success -or -not $response.Data.success) {
    Write-TestResult -TestName "User Registration (Duplicate)" -Passed $true -Details "Correctly rejected duplicate username"
} else {
    Write-TestResult -TestName "User Registration (Duplicate)" -Passed $false -Details "Should reject duplicate username"
}

# Test 1.3: SQL Injection in Registration
Write-Host "`nTest 1.3: SQL Injection Protection in Registration"
$sqlInjectionUser = @{
    username = "admin' OR '1'='1"
    email = "sqlinject@test.com"
    password = "' OR '1'='1' --"
}
$response = Invoke-ApiRequest -Endpoint "api/auth/register.php" -Method "POST" -Body $sqlInjectionUser -ExpectError $true

if (-not $response.Success -or ($response.Data -and -not $response.Data.success)) {
    Write-TestResult -TestName "SQL Injection Protection (Register)" -Passed $true -Details "SQL injection attempt blocked"
} else {
    Write-TestResult -TestName "SQL Injection Protection (Register)" -Passed $false -Details "⚠️ SECURITY RISK: SQL injection possible"
}

# Test 1.4: User Login - Valid Credentials
Write-Host "`nTest 1.4: User Login with Valid Credentials"
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$loginData = @{
    username = $testUser.username
    password = $testUser.password
}
$response = Invoke-ApiRequest -Endpoint "api/auth/login.php" -Method "POST" -Body $loginData -Session $session

if ($response.Success -and $response.Data.success) {
    Write-TestResult -TestName "User Login (Valid)" -Passed $true -Details "Login successful, session established"
    $userSession = $response.Session
} else {
    Write-TestResult -TestName "User Login (Valid)" -Passed $false -Details "Login failed" -Expected "Successful login" -Actual $response.Error
}

# Test 1.5: User Login - Invalid Password
Write-Host "`nTest 1.5: User Login with Invalid Password (Should Fail)"
$invalidLogin = @{
    username = $testUser.username
    password = "WrongPassword123"
}
$response = Invoke-ApiRequest -Endpoint "api/auth/login.php" -Method "POST" -Body $invalidLogin -ExpectError $true

if (-not $response.Success -or -not $response.Data.success) {
    Write-TestResult -TestName "User Login (Invalid Password)" -Passed $true -Details "Correctly rejected invalid password"
} else {
    Write-TestResult -TestName "User Login (Invalid Password)" -Passed $false -Details "⚠️ SECURITY RISK: Accepted invalid password"
}

# Test 1.6: SQL Injection in Login
Write-Host "`nTest 1.6: SQL Injection Protection in Login"
$sqlInjectionLogin = @{
    username = "admin' OR '1'='1' --"
    password = "anything"
}
$response = Invoke-ApiRequest -Endpoint "api/auth/login.php" -Method "POST" -Body $sqlInjectionLogin -ExpectError $true

if (-not $response.Success -or -not $response.Data.success) {
    Write-TestResult -TestName "SQL Injection Protection (Login)" -Passed $true -Details "SQL injection attempt blocked"
} else {
    Write-TestResult -TestName "SQL Injection Protection (Login)" -Passed $false -Details "⚠️ CRITICAL SECURITY RISK: SQL injection in login!"
}

# Test 1.7: Admin Login
Write-Host "`nTest 1.7: Admin Login"
$adminSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$response = Invoke-ApiRequest -Endpoint "api/auth/login.php" -Method "POST" -Body $testAdmin -Session $adminSession

if ($response.Success -and $response.Data.success) {
    Write-TestResult -TestName "Admin Login" -Passed $true -Details "Admin logged in successfully"
    $adminSession = $response.Session
} else {
    Write-TestResult -TestName "Admin Login" -Passed $false -Details "Admin login failed - tests requiring admin will be skipped"
}

# ====================================================================
# TEST SUITE 2: Task Management API
# ====================================================================

Write-TestHeader "TEST SUITE 2: Task Management API"

# Test 2.1: List Tasks (Authenticated)
Write-Host "`nTest 2.1: List Tasks (Authenticated User)"
$response = Invoke-ApiRequest -Endpoint "api/tasks/list.php" -Method "GET" -Session $userSession

if ($response.Success -and $response.Data) {
    Write-TestResult -TestName "List Tasks (Authenticated)" -Passed $true -Details "Retrieved task list successfully"
    $tasks = $response.Data
} else {
    Write-TestResult -TestName "List Tasks (Authenticated)" -Passed $false -Details "Failed to retrieve tasks"
}

# Test 2.2: List Tasks (Unauthenticated - Should Fail)
Write-Host "`nTest 2.2: List Tasks Without Authentication (Should Fail)"
$response = Invoke-ApiRequest -Endpoint "api/tasks/list.php" -Method "GET" -ExpectError $true

if (-not $response.Success -or $response.StatusCode -eq 401) {
    Write-TestResult -TestName "List Tasks (Unauthenticated)" -Passed $true -Details "Correctly blocked unauthenticated access"
} else {
    Write-TestResult -TestName "List Tasks (Unauthenticated)" -Passed $false -Details "⚠️ SECURITY RISK: Allowed unauthenticated access"
}

# Test 2.3: Get Specific Task Details
Write-Host "`nTest 2.3: Get Specific Task Details"
if ($tasks -and $tasks.Count -gt 0) {
    $firstTaskId = $tasks[0].id
    $response = Invoke-ApiRequest -Endpoint "api/tasks/get.php?id=$firstTaskId" -Method "GET" -Session $userSession
    
    if ($response.Success -and $response.Data) {
        Write-TestResult -TestName "Get Task Details" -Passed $true -Details "Retrieved task details for ID $firstTaskId"
    } else {
        Write-TestResult -TestName "Get Task Details" -Passed $false -Details "Failed to retrieve task details"
    }
} else {
    Write-TestResult -TestName "Get Task Details" -Passed $false -Details "No tasks available to test (Skipped)"
}

# ====================================================================
# TEST SUITE 3: File Operations API
# ====================================================================

Write-TestHeader "TEST SUITE 3: File Operations API"

# Test 3.1: Path Traversal Attack Prevention
Write-Host "`nTest 3.1: Path Traversal Attack Prevention"
$pathTraversalAttempts = @(
    "../../../etc/passwd",
    "..\..\..\..\windows\system32\config\sam",
    "....//....//....//etc/passwd"
)

$pathTraversalBlocked = $true
foreach ($maliciousPath in $pathTraversalAttempts) {
    $response = Invoke-ApiRequest -Endpoint "api/files/read.php" -Method "POST" -Body @{ path = $maliciousPath } -Session $userSession -ExpectError $true
    
    if ($response.Success -and $response.Data -and $response.Data.content) {
        $pathTraversalBlocked = $false
        break
    }
}

if ($pathTraversalBlocked) {
    Write-TestResult -TestName "Path Traversal Protection" -Passed $true -Details "All path traversal attempts blocked"
} else {
    Write-TestResult -TestName "Path Traversal Protection" -Passed $false -Details "⚠️ CRITICAL SECURITY RISK: Path traversal possible!"
}

# Test 3.2: File Upload/Create Validation
Write-Host "`nTest 3.2: File Upload with Malicious Filename"
$maliciousFilenames = @(
    "../../../malicious.php",
    "test.php.jpg",
    [string]"<script>alert('xss')</script>.txt"
)

$filenameValidationWorks = $true
foreach ($maliciousName in $maliciousFilenames) {
    $response = Invoke-ApiRequest -Endpoint "api/files/create.php" -Method "POST" -Body @{ 
        path = $maliciousName
        content = "test"
    } -Session $userSession -ExpectError $true
    
    if ($response.Success -and $response.Data.success) {
        $filenameValidationWorks = $false
        break
    }
}

if ($filenameValidationWorks) {
    Write-TestResult -TestName "Malicious Filename Protection" -Passed $true -Details "Malicious filenames rejected"
} else {
    Write-TestResult -TestName "Malicious Filename Protection" -Passed $false -Details "⚠️ SECURITY RISK: Malicious filenames accepted"
}

# ====================================================================
# TEST SUITE 4: Input Validation and XSS Protection
# ====================================================================

Write-TestHeader "TEST SUITE 4: Input Validation and XSS Protection"

# Test 4.1: XSS in Task Title (Admin Required)
Write-Host "`nTest 4.1: XSS Protection in Task Creation"
if ($adminSession) {
    $xssPayloads = @(
        [string]"<script>alert('XSS')</script>",
        [string]"<img src=x onerror=alert('XSS')>",
        [string]"javascript:alert('XSS')"
    )
    
    $xssBlocked = $true
    foreach ($payload in $xssPayloads) {
        $taskData = @{
            title = $payload
            description = "Test"
            type = "code"
        }
        $response = Invoke-ApiRequest -Endpoint "api/tasks/create.php" -Method "POST" -Body $taskData -Session $adminSession
        
        # If response contains unescaped payload, XSS is possible
        if ($response.Content -match [regex]::Escape($payload)) {
            $xssBlocked = $false
            break
        }
    }
    
    if ($xssBlocked) {
        Write-TestResult -TestName "XSS Protection (Task Title)" -Passed $true -Details "XSS payloads properly escaped"
    } else {
        Write-TestResult -TestName "XSS Protection (Task Title)" -Passed $false -Details "⚠️ SECURITY RISK: XSS possible in task titles"
    }
} else {
    Write-TestResult -TestName "XSS Protection (Task Title)" -Passed $false -Details "Skipped (No admin session)"
}

# Test 4.2: Large Input Handling (DoS Prevention)
Write-Host "`nTest 4.2: Large Input Handling (DoS Prevention)"
$largeString = "A" * 1000000  # 1MB string
$response = Invoke-ApiRequest -Endpoint "api/auth/register.php" -Method "POST" -Body @{
    username = $largeString
    email = "test@test.com"
    password = "Test123"
} -ExpectError $true

if (-not $response.Success -or -not $response.Data.success) {
    Write-TestResult -TestName "Large Input Rejection" -Passed $true -Details "Large input rejected (DoS protection)"
} else {
    Write-TestResult -TestName "Large Input Rejection" -Passed $false -Details "⚠️ RISK: Large inputs accepted (possible DoS vector)"
}

# ====================================================================
# TEST SUITE 5: Session Management
# ====================================================================

Write-TestHeader "TEST SUITE 5: Session Management"

# Test 5.1: Session Persistence
Write-Host "`nTest 5.1: Session Persistence Across Requests"
$response1 = Invoke-ApiRequest -Endpoint "api/tasks/list.php" -Method "GET" -Session $userSession
$response2 = Invoke-ApiRequest -Endpoint "api/tasks/list.php" -Method "GET" -Session $userSession

if ($response1.Success -and $response2.Success) {
    Write-TestResult -TestName "Session Persistence" -Passed $true -Details "Session maintained across requests"
} else {
    Write-TestResult -TestName "Session Persistence" -Passed $false -Details "Session not maintained"
}

# Test 5.2: Logout Functionality
Write-Host "`nTest 5.2: Logout Functionality"
$response = Invoke-ApiRequest -Endpoint "api/auth/logout.php" -Method "POST" -Session $userSession

if ($response.Success) {
    # Try to access protected endpoint after logout
    $response2 = Invoke-ApiRequest -Endpoint "api/tasks/list.php" -Method "GET" -Session $userSession -ExpectError $true
    
    if (-not $response2.Success -or $response2.StatusCode -eq 401) {
        Write-TestResult -TestName "Logout Functionality" -Passed $true -Details "Session invalidated after logout"
    } else {
        Write-TestResult -TestName "Logout Functionality" -Passed $false -Details "⚠️ SECURITY RISK: Session still valid after logout"
    }
} else {
    Write-TestResult -TestName "Logout Functionality" -Passed $false -Details "Logout endpoint failed"
}

# ====================================================================
# TEST SUITE 6: Error Handling
# ====================================================================

Write-TestHeader "TEST SUITE 6: Error Handling"

# Test 6.1: Missing Required Fields
Write-Host "`nTest 6.1: Missing Required Fields in Registration"
$incompleteData = @{
    username = "testuser"
}
$response = Invoke-ApiRequest -Endpoint "api/auth/register.php" -Method "POST" -Body $incompleteData -ExpectError $true

if (-not $response.Success -or -not $response.Data.success) {
    Write-TestResult -TestName "Missing Required Fields" -Passed $true -Details "Correctly rejected incomplete data"
} else {
    Write-TestResult -TestName "Missing Required Fields" -Passed $false -Details "Accepted incomplete registration data"
}

# Test 6.2: Invalid JSON
Write-Host "`nTest 6.2: Invalid JSON Handling"
try {
    $response = Invoke-WebRequest -Uri "$baseUrl/api/auth/login.php" -Method POST -Body "not json{invalid}" -ContentType "application/json" -UseBasicParsing -ErrorAction Stop
    $jsonHandlingWorks = $false
} catch {
    $jsonHandlingWorks = $true
}

if ($jsonHandlingWorks) {
    Write-TestResult -TestName "Invalid JSON Handling" -Passed $true -Details "Invalid JSON rejected appropriately"
} else {
    Write-TestResult -TestName "Invalid JSON Handling" -Passed $false -Details "Invalid JSON not handled correctly"
}

# Test 6.3: Non-Existent Endpoint
Write-Host "`nTest 6.3: Non-Existent Endpoint"
$response = Invoke-ApiRequest -Endpoint "api/nonexistent/endpoint.php" -Method "GET" -ExpectError $true

if ($response.StatusCode -eq 404) {
    Write-TestResult -TestName "Non-Existent Endpoint" -Passed $true -Details "404 returned for non-existent endpoint"
} else {
    Write-TestResult -TestName "Non-Existent Endpoint" -Passed $false -Details "Unexpected response for non-existent endpoint"
}

# ====================================================================
# FINAL REPORT
# ====================================================================

Write-TestHeader "TEST SUITE COMPLETE - FINAL REPORT"

Write-Host "`n📊 Test Summary:" -ForegroundColor Cyan
Write-Host "  Total Tests: $testCounter"
Write-Host "  ${GREEN}Passed: $passedTests$RESET" -ForegroundColor Green
Write-Host "  ${RED}Failed: $failedTests$RESET" -ForegroundColor Red
Write-Host "  Success Rate: $([math]::Round(($passedTests / $testCounter) * 100, 2))%" -ForegroundColor Cyan

# Security Issues Summary
$securityIssues = $testResults | Where-Object { -not $_.Passed -and $_.Details -like "*SECURITY RISK*" }
if ($securityIssues.Count -gt 0) {
    Write-Host "`n${RED}⚠️  SECURITY ISSUES FOUND: $($securityIssues.Count)$RESET" -ForegroundColor Red
    foreach ($issue in $securityIssues) {
        Write-Host "  • TEST $($issue.Number): $($issue.Test)" -ForegroundColor Red
        Write-Host "    $($issue.Details)" -ForegroundColor Yellow
    }
} else {
    Write-Host "`n${GREEN}✓ NO CRITICAL SECURITY ISSUES DETECTED$RESET" -ForegroundColor Green
}

# Failed Tests (Non-Security)
$nonSecurityFailures = $testResults | Where-Object { -not $_.Passed -and $_.Details -notlike "*SECURITY RISK*" -and $_.Details -notlike "*Skipped*" }
if ($nonSecurityFailures.Count -gt 0) {
    Write-Host "`n${YELLOW}⚠️  OTHER FAILURES: $($nonSecurityFailures.Count)$RESET" -ForegroundColor Yellow
    foreach ($failure in $nonSecurityFailures) {
        Write-Host "  • TEST $($failure.Number): $($failure.Test)" -ForegroundColor Yellow
        Write-Host "    $($failure.Details)" -ForegroundColor Gray
    }
}

# Export detailed report to JSON
$reportPath = "c:\xampp\htdocs\pythonIDE\scripts\test-report-$(Get-Date -Format 'yyyyMMdd-HHmmss').json"
$testResults | ConvertTo-Json -Depth 10 | Out-File -FilePath $reportPath -Encoding UTF8

Write-Host "`n📄 Detailed report saved to: $reportPath" -ForegroundColor Cyan
Write-Host ""
Write-Host "========================================" -ForegroundColor Blue
Write-Host "Testing Complete - $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')" -ForegroundColor Blue
Write-Host "========================================" -ForegroundColor Blue
Write-Host ""

# Return summary object
return @{
    TotalTests = $testCounter
    Passed = $passedTests
    Failed = $failedTests
    SecurityIssues = $securityIssues.Count
    ReportPath = $reportPath
}
