param(
    [Parameter(Mandatory=$true)]
    [string]$SourceXML,

    [Parameter(Mandatory=$true)]
    [string]$OutputOTS,

    [Parameter(Mandatory=$true)]
    [string]$MasterTemplate
)

try {
    # 1. Copy the master OTS template to the final output location
    Copy-Item -Path $MasterTemplate -Destination $OutputOTS -Force

    # 2. Rename .ots to .zip so we can natively manipulate the archive
    $TempZip = "$OutputOTS.zip"
    Rename-Item -Path $OutputOTS -NewName (Split-Path $TempZip -Leaf)

    # 3. Create a temp folder replicating the internal ODS/OTS structure
    #    The file we need to replace is always content.xml at the archive root
    $TempFolder = Join-Path $env:TEMP "ots_generation_$(Get-Random)"
    New-Item -ItemType Directory -Path $TempFolder -Force | Out-Null

    # Copy the PHP-generated XML into temp folder as content.xml
    $TargetXML = Join-Path $TempFolder "content.xml"
    Copy-Item -Path $SourceXML -Destination $TargetXML -Force

    # 4. Use Compress-Archive -Update to inject the new content.xml into the zip
    #    This overwrites the dummy placeholder data from the master template
    Compress-Archive -Path $TargetXML -Update -DestinationPath $TempZip

    # 5. Rename back to .ots
    Rename-Item -Path $TempZip -NewName (Split-Path $OutputOTS -Leaf)

    # 6. Clean up temp folder
    Remove-Item -Path $TempFolder -Recurse -Force | Out-Null

    # Output success marker for PHP to detect
    Write-Output "SUCCESS"
} catch {
    Write-Error $_.Exception.Message
    exit 1
}
