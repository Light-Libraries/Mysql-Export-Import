# Mysql-Export-Import

Php Light Libray to Export and Import Mysql database and tables

- create `config.json`
- set operation type `operationType` = `import` or `export`
- set import configuration includes [
  `serverName`: 'localhost',
  `username`: 'root',
  `password`: 'password',
  `port`: '3306',
  `databaseName`: 'import_test'
  ]
- set export configuration includes [
  `serverName`: 'localhost',
  `username`: 'root',
  `password`: 'password',
  `port`: '3306',
  `databaseName`: 'export_test',
  `tables`:[

  ]
  ]

- set array list of `tables` to export or set `[]` empty array to export all tables
