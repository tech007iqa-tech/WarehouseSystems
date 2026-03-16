param(
    [Parameter(Mandatory=$true)]
    [string]$SourceXML,

    [Parameter(Mandatory=$true)]
    [string]$OutputOTS,

    [Parameter(Mandatory=$true)]
    [string]$MasterTemplate
)

try {
    # 1. Check if output file is in use
    if (Test-Path $OutputOTS) {
        try {
            $stream = [System.IO.File]::OpenWrite($OutputOTS)
            $stream.Close()
        } catch {
            Write-Output "ERROR: The file is currently open in another program (LibreOffice Calc). Please close it and try again."
            exit 1
        }
    }

    # 2. Copy the master OTS template to the final output location
    Copy-Item -Path $MasterTemplate -Destination $OutputOTS -Force

    # 3. Use .NET ZipArchive to modify the file directly
    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem

    $zip = [System.IO.Compression.ZipFile]::Open($OutputOTS, "Update")
    
    # Remove the dummy placeholder content.xml
    $entry = $zip.GetEntry("content.xml")
    if ($entry) {
        $entry.Delete()
    }

    # Inject the actual business data XML
    $xmlContent = [System.IO.File]::ReadAllText($SourceXML)
    $newEntry = $zip.CreateEntry("content.xml", [System.IO.Compression.CompressionLevel]::Optimal)
    $writer = New-Object System.IO.StreamWriter($newEntry.Open())
    $writer.Write($xmlContent)
    $writer.Dispose()
    
    # Close and finalize the archive
    $zip.Dispose()

    # Output success marker for PHP
    Write-Output "SUCCESS"
} catch {
    Write-Output "ERROR: $($_.Exception.Message)"
    exit 1
}
