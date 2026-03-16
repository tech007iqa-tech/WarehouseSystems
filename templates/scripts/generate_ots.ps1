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

    # 2. Get the new XML content from PHP
    $NewInnerXML = [System.IO.File]::ReadAllText($SourceXML)

    # 3. Copy the master OTS template to the final output location
    Copy-Item -Path $MasterTemplate -Destination $OutputOTS -Force

    # 4. Use .NET ZipArchive to modify the file directly
    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem

    $zip = [System.IO.Compression.ZipFile]::Open($OutputOTS, "Update")

    # --- CLEAN: Remove entries that trigger LibreOffice macro security scanner ---
    $toDelete = @("Configurations2/", "Thumbnails/thumbnail.png", "manifest.rdf")
    foreach ($name in $toDelete) {
        $e = $zip.GetEntry($name)
        if ($e) { $e.Delete() }
    }

    # Rebuild a clean, minimal manifest.xml
    $mfEntry = $zip.GetEntry("META-INF/manifest.xml")
    $mfReader = New-Object System.IO.StreamReader($mfEntry.Open())
    $mfReader.Close()
    $mfXML = '<?xml version="1.0" encoding="UTF-8"?>' + "`n" +
        '<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.2">' + "`n" +
        ' <manifest:file-entry manifest:full-path="/" manifest:version="1.2" manifest:media-type="application/vnd.oasis.opendocument.spreadsheet-template"/>' + "`n" +
        ' <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>' + "`n" +
        ' <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>' + "`n" +
        ' <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>' + "`n" +
        ' <manifest:file-entry manifest:full-path="settings.xml" manifest:media-type="text/xml"/>' + "`n" +
        '</manifest:manifest>'
    $mfEntry.Delete()
    $mfNew = $zip.CreateEntry("META-INF/manifest.xml", [System.IO.Compression.CompressionLevel]::Optimal)
    $mfWriter = New-Object System.IO.StreamWriter($mfNew.Open())
    $mfWriter.Write($mfXML)
    $mfWriter.Dispose()

    $entry = $zip.GetEntry("content.xml")
    
    # Read the master content.xml
    $stream = $entry.Open()
    $reader = New-Object System.IO.StreamReader($stream)
    $MasterXML = $reader.ReadToEnd()
    $reader.Close()
    $stream.Close()

    # --- SURGERY: Replace Internal Spreadsheet ---
    $Pattern = '(?s)<office:spreadsheet.*?>.*?</office:spreadsheet>'
    $Replacement = "<office:spreadsheet>$NewInnerXML</office:spreadsheet>"
    $FinalXML = [regex]::Replace($MasterXML, $Pattern, $Replacement)

    # 5. Write the surgically altered XML back
    $entry.Delete()
    $newEntry = $zip.CreateEntry("content.xml", [System.IO.Compression.CompressionLevel]::Optimal)
    $writer = New-Object System.IO.StreamWriter($newEntry.Open())
    $writer.Write($FinalXML)
    $writer.Dispose()
    
    # 6. Finalize
    $zip.Dispose()

    Write-Output "SUCCESS"
} catch {
    Write-Output "ERROR: $($_.Exception.Message)"
    exit 1
}
