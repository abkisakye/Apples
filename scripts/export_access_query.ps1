param(
    [Parameter(Mandatory = $true)]
    [string]$DatabasePath,

    [Parameter(Mandatory = $true)]
    [string]$Query
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$connectionString = "Provider=Microsoft.ACE.OLEDB.12.0;Data Source=$DatabasePath;"
$connection = New-Object System.Data.OleDb.OleDbConnection($connectionString)

try {
    $connection.Open()
    $command = $connection.CreateCommand()
    $command.CommandText = $Query
    $reader = $command.ExecuteReader()

    while ($reader.Read()) {
        $row = [ordered]@{}

        for ($i = 0; $i -lt $reader.FieldCount; $i++) {
            $name = $reader.GetName($i)
            $value = if ($reader.IsDBNull($i)) { $null } else { $reader.GetValue($i) }

            if ($value -is [datetime]) {
                $value = $value.ToString('yyyy-MM-dd HH:mm:ss')
            }

            $row[$name] = $value
        }

        $row | ConvertTo-Json -Compress -Depth 5
    }
}
finally {
    if ($null -ne $reader) {
        $reader.Close()
    }

    if ($null -ne $connection) {
        $connection.Close()
    }
}
