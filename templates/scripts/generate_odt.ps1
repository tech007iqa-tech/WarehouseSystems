param(
    [Parameter(Mandatory=$true)]
    [string]$SourceXML,

    [Parameter(Mandatory=$true)]
    [string]$OutputODT,

    [Parameter(Mandatory=$true)]
    [string]$MasterTemplate
)

try {
    # 1. Check if output file is in use
    if (Test-Path $OutputODT) {
        try {
            $stream = [System.IO.File]::OpenWrite($OutputODT)
            $stream.Close()
        } catch {
            Write-Output "ERROR: The file is currently open in another program (LibreOffice). Please close it and try again."
            exit 1
        }
    }

    # 2. Get the new XML content from PHP
    $NewInnerXML = [System.IO.File]::ReadAllText($SourceXML)

    # 3. Create a copy of the Master Template
    Copy-Item -Path $MasterTemplate -Destination $OutputODT -Force

    # 4. Use .NET ZipArchive to perform surgery on content.xml
    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem

    $zip = [System.IO.Compression.ZipFile]::Open($OutputODT, "Update")

    # --- CLEAN: Remove entries that trigger LibreOffice macro security scanner ---
    # Configurations2/ is a macro config bundle — its presence alone causes the
    # "macros disabled" warning and "General I/O Error" on generated documents.
    $toDelete = @("Configurations2/", "Thumbnails/thumbnail.png", "manifest.rdf")
    foreach ($name in $toDelete) {
        $e = $zip.GetEntry($name)
        if ($e) { $e.Delete() }
    }

    # Update manifest.xml to remove references to deleted entries
    $mfEntry = $zip.GetEntry("META-INF/manifest.xml")
    $mfReader = New-Object System.IO.StreamReader($mfEntry.Open())
    $mfXML = $mfReader.ReadToEnd()
    $mfReader.Close()
    $mfXML = $mfXML -replace '<manifest:file-entry[^/]*/>', ''
    # Re-add valid entries only
    $mfXML = '<?xml version="1.0" encoding="UTF-8"?>' + "`n" +
        '<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.2">' + "`n" +
        ' <manifest:file-entry manifest:full-path="/" manifest:version="1.2" manifest:media-type="application/vnd.oasis.opendocument.text"/>' + "`n" +
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

    # --- SURGERY: Inject New Styles ---
    # We inject specific styles to ensure the label matches our "One Label" layout requirements.
    $PBStyle    = '<style:style style:name="PB" style:family="paragraph"><style:paragraph-properties fo:break-before="page"/></style:style>'
    $TitleStyle = '<style:style style:name="P3" style:family="paragraph" style:parent-style-name="Standard"><style:paragraph-properties fo:text-align="center" fo:margin-bottom="0.05in"/><style:text-properties fo:font-size="20pt" fo:font-weight="bold"/></style:style>'
    $SpecStyle  = '<style:style style:name="Standard" style:family="paragraph" style:class="text"><style:paragraph-properties fo:margin-top="0in" fo:margin-bottom="0.02in" fo:line-height="100%"/><style:text-properties fo:font-size="10pt"/></style:style>'
    
    $AllStyles = $PBStyle + $TitleStyle + $SpecStyle

    if ($MasterXML -match '<office:automatic-styles>') {
        $MasterXML = $MasterXML -replace '<office:automatic-styles>', "<office:automatic-styles>$AllStyles"
    } else {
        # If block doesn't exist, create it after font-face-decls or before body
        $MasterXML = $MasterXML -replace '</office:font-face-decls>', "</office:font-face-decls><office:automatic-styles>$AllStyles</office:automatic-styles>"
    }

    # --- SURGERY: Replace Internal Body ---
    # We replace everything inside <office:text>...</office:text>
    # Note: Modern ODTs might have <office:text> followed by sequence-decls.
    # We'll just replace everything from <office:text> to </office:text>, but keep the tags themselves.
    $Pattern = '(?s)<office:text.*?>.*?</office:text>'
    $Replacement = "<office:text>$NewInnerXML</office:text>"
    $FinalXML = [regex]::Replace($MasterXML, $Pattern, $Replacement)

    # 5. Write the surgically altered XML back to the entry
    # Note: We must delete and recreate the entry to update its size correctly
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
