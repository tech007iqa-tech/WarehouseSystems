param(
    [Parameter(Mandatory=$true)]
    [string]$SourceXML,

    [Parameter(Mandatory=$true)]
    [string]$OutputODT,

    [Parameter(Mandatory=$true)]
    [string]$MasterTemplate
)

try {
    # 1. Create a copy of the Master Template into the final output location
    Copy-Item -Path $MasterTemplate -Destination $OutputODT -Force

    # 2. Get the full path to exactly where PowerShell zip wants it.
    # We rename the .odt to .zip to natively manipulate it without custom libraries
    $TempZip = "$OutputODT.zip"
    Rename-Item -Path $OutputODT -NewName (Split-Path $TempZip -Leaf)

    # 3. Create a temporary folder structure that matches the internal ODT format
    # The xml we want to replace is always at the root of the archive named content.xml
    $TempFolder = Join-Path $env:TEMP "odt_generation_$(Get-Random)"
    New-Item -ItemType Directory -Path $TempFolder -Force | Out-Null
    
    # Copy the raw XML PHP made into this temp folder as "content.xml"
    $TargetXML = Join-Path $TempFolder "content.xml"
    Copy-Item -Path $SourceXML -Destination $TargetXML -Force
    
    # 4. Use Compress-Archive with the -Update switch to push the new content.xml inside the zip
    # This overwrites the old dummy content.xml
    Compress-Archive -Path $TargetXML -Update -DestinationPath $TempZip
    
    # 5. Rename it back to .odt
    Rename-Item -Path $TempZip -NewName (Split-Path $OutputODT -Leaf)
    
    # 6. Clean up temporary un-zipped folder
    Remove-Item -Path $TempFolder -Recurse -Force | Out-Null
    
    # Output success marker for PHP
    Write-Output "SUCCESS"
} catch {
    Write-Error $_.Exception.Message
    exit 1
}
