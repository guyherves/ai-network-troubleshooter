import pymysql
import os

with open('database.sql', 'r') as f:
    sql_script = f.read()

# Connect without DB to ensure we can create it if needed
connection = pymysql.connect(host='localhost', user='root', password='mysql')
cursor = connection.cursor()

# Simple script execution by splitting on ';'
# Note: This is basic and might fail if there are ';' inside strings, but for this simple SQL it should work.
# A better way is to execute it statement by statement properly, but let's try this first.
statements = sql_script.split(';')
for statement in statements:
    if statement.strip():
        cursor.execute(statement)

connection.commit()
connection.close()
print("Database imported successfully!")
