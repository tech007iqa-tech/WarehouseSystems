param(
    [Parameter(Mandatory=$true)]
    [string]$FilePath
)

Add-Type -AssemblyName System.IO.Compression.FileSystem

Write-Host "=== ZIP INSPECTION: $FilePath ===" -ForegroundColor Cyan

if (-not (Test-Path $FilePath)) {
    Write-Host "[ERROR] File not found." -ForegroundColor Red
    exit 1
}

$zip = [System.IO.Compression.ZipFile]::OpenRead($FilePath)

Write-Host "`n[ZIP CONTENTS]"
$i = 0
foreach ($e in $zip.Entries) {
    $method = if ($e.CompressedLength -eq $e.Length) { "STORED " } else { "DEFLATE" }
    Write-Host "[$i] $method  $($e.FullName)"
    $i++
}

Write-Host "`n[MANIFEST.XML]" -ForegroundColor Yellow
$mf = $zip.GetEntry("META-INF/manifest.xml")
if ($mf) {
    $r = New-Object System.IO.StreamReader($mf.Open())
    Write-Host $r.ReadToEnd()
    $r.Close()
} else {
    Write-Host "(missing)" -ForegroundColor Red
}

$zip.Dispose()
