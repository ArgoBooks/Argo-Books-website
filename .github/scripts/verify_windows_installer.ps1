# Unpacks the Windows installer listed in avalonia-update.xml and checks that every Argo Books
# DLL and EXE inside it carries the release version. 2.0.15 shipped ArgoBooks.Core.dll and
# ArgoBooks.dll from 2.0.14, and the app exited at launch without showing anything.
#
# Usage: verify_windows_installer.ps1 -AppcastPath avalonia-update.xml [-InstallerPath local.exe]

param(
    [Parameter(Mandatory)] [string] $AppcastPath,
    [string] $InstallerPath
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

[xml] $feed = Get-Content -Raw $AppcastPath
$ns = New-Object System.Xml.XmlNamespaceManager($feed.NameTable)
$ns.AddNamespace('sparkle', 'http://www.andymatuschak.org/xml-namespaces/sparkle')
$enclosure = $feed.SelectSingleNode("//enclosure[@sparkle:os='windows']", $ns)
if (-not $enclosure) { Write-Host "::error::No windows enclosure in $AppcastPath"; exit 1 }
$version = $enclosure.GetAttribute('version', 'http://www.andymatuschak.org/xml-namespaces/sparkle')

$work = Join-Path ([IO.Path]::GetTempPath()) "argo-installer-$version"
Remove-Item -Recurse -Force $work -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Force "$work\msi", "$work\files" | Out-Null

if (-not $InstallerPath) {
    $InstallerPath = "$work\installer.exe"
    Invoke-WebRequest $enclosure.url -OutFile $InstallerPath -UserAgent 'ArgoBooks-release-verifier (GitHub Actions)'
}

$extract = Start-Process $InstallerPath -ArgumentList "/extract:`"$work\msi`"" -Wait -PassThru
$cabs = Get-ChildItem "$work\msi" -Filter *.cab
if ($extract.ExitCode -ne 0 -or -not $cabs) {
    Write-Host "::error::Could not unpack the installer (exit code $($extract.ExitCode))"
    exit 1
}

foreach ($cab in $cabs) {
    expand.exe $cab.FullName '-F:ArgoBooks*' "$work\files" | Out-Null
}

# Cab entries are named by MSI file key, e.g. "Argo Books.dll" is stored as ArgoBooks.dll_1.
$binaries = Get-ChildItem "$work\files" | Where-Object { $_.Name -match '\.(dll|exe)(_\d+)?$' }

$failed = $false
foreach ($required in 'ArgoBooks.Core.dll', 'ArgoBooks.dll', 'ArgoBooks.Shared.dll', 'ArgoBooks.exe') {
    if (-not ($binaries | Where-Object Name -eq $required)) {
        Write-Host "::error::$required is not in the installer"
        $failed = $true
    }
}

$expected = [version] $version
foreach ($file in $binaries) {
    $raw = $file.VersionInfo
    $actual = [version]::new($raw.FileMajorPart, $raw.FileMinorPart, $raw.FileBuildPart)
    if ($actual -eq $expected) {
        Write-Host "$($file.Name): $actual"
    } else {
        Write-Host "::error::$($file.Name) is version $actual, expected $version"
        $failed = $true
    }
}

Remove-Item -Recurse -Force $work -ErrorAction SilentlyContinue
if ($failed) { exit 1 }
Write-Host "All Argo Books binaries in the $version installer are version $version"
