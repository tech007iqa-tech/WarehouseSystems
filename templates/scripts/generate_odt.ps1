param(
    [Parameter(Mandatory=$true)]
    [string]$SourceXML,

    [Parameter(Mandatory=$true)]
    [string]$OutputODT,

    [Parameter(Mandatory=$true)]
    [string]$MasterTemplate
)

try {
    # 1. Check if output file is in use before copying
    if (Test-Path $OutputODT) {
        try {
            # Try to open the file for writing to see if it's locked
            $stream = [System.IO.File]::OpenWrite($OutputODT)
            $stream.Close()
        } catch {
            Write-Output "ERROR: The file is currently open in another program (LibreOffice). Please close it and try again."
            exit 1
        }
    }

    # 2. Create a copy of the Master Template into the final output location
    Copy-Item -Path $MasterTemplate -Destination $OutputODT -Force

    # 3. Use .NET ZipArchive to modify the file directly
    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem

    $zip = [System.IO.Compression.ZipFile]::Open($OutputODT, "Update")
    
    # Locate the dummy content.xml and remove it
    $entry = $zip.GetEntry("content.xml")
    if ($entry) {
        $entry.Delete()
    }

    # Add the new XML from the PHP temp file
    # We use File::ReadAllText to ensure no BOM
    $xmlContent = [System.IO.File]::ReadAllText($SourceXML)
    $newEntry = $zip.CreateEntry("content.xml", [System.IO.Compression.CompressionLevel]::Optimal)
    $writer = New-Object System.IO.StreamWriter($newEntry.Open())
    $writer.Write($xmlContent)
    $writer.Dispose()
    
    # Close and save the ZIP
    $zip.Dispose()

    # Output success marker for PHP
    Write-Output "SUCCESS"
} catch {
    Write-Output "ERROR: $($_.Exception.Message)"
    exit 1
}
