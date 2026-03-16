param(
    [Parameter(Mandatory=$true)]
    [string]$FilePath
)

function Write-Verify {
    param($Msg, $Success=$true)
    if ($Success) { Write-Host "[OK] $Msg" -ForegroundColor Green }
    else { Write-Host "[FAIL] $Msg" -ForegroundColor Red }
}

try {
    Write-Host "Verifying: $FilePath" -ForegroundColor Cyan

    if (-not (Test-Path $FilePath)) {
        Write-Verify "File exists" $false
        return
    }
    Write-Verify "File exists"

    # 1. Check ZIP signature (first 2 bytes should be PK)
    $bytes = [System.IO.File]::ReadAllBytes($FilePath)
    if ($bytes[0] -eq 0x50 -and $bytes[1] -eq 0x4B) {
        Write-Verify "ZIP Magic Number (PK)"
    } else {
        Write-Verify "ZIP Magic Number (PK)" $false
    }

    # 2. Open archive
    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $zip = [System.IO.Compression.ZipFile]::OpenRead($FilePath)

    # 3. Check for mandatory files
    $required = @("mimetype", "content.xml", "META-INF/manifest.xml")
    foreach ($r in $required) {
        $entry = $zip.GetEntry($r)
        if ($entry) {
            Write-Verify "Found $r"
        } else {
            Write-Verify "Found $r" $false
        }
    }

    # 4. Check mimetype compression (must be Uncompressed)
    $mEntry = $zip.GetEntry("mimetype")
    if ($mEntry) {
        if ($mEntry.CompressedLength -eq $mEntry.Length) {
            Write-Verify "mimetype is uncompressed"
        } else {
            Write-Verify "mimetype is uncompressed (Current: $($mEntry.CompressedLength) vs $($mEntry.Length))" $false
        }
    }

    # 5. Check content.xml well-formedness
    $cEntry = $zip.GetEntry("content.xml")
    if ($cEntry) {
        $reader = New-Object System.IO.StreamReader($cEntry.Open())
        $xmlText = $reader.ReadToEnd()
        $reader.Close()

        try {
            [xml]$xml = $xmlText
            Write-Verify "content.xml is well-formed XML"
        } catch {
            Write-Verify "content.xml is well-formed XML ($($_.Exception.Message))" $false
        }
    }

    $zip.Dispose()
    Write-Host "`nVerification Complete." -ForegroundColor Cyan

} catch {
    Write-Host "System Error: $($_.Exception.Message)" -ForegroundColor Red
}
