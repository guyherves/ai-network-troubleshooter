import pymysql
import os

try:
    # Connect to MySQL server without specifying a database
    connection = pymysql.connect(
        host='localhost',
        user='root',
        password='mysql'
    )
    
    with connection.cursor() as cursor:
        cursor.execute("CREATE DATABASE IF NOT EXISTS network_troubleshooting")
        print("Database 'network_troubleshooting' created successfully (or already exists)!")
        
    connection.commit()
    connection.close()
    
except Exception as e:
    print(f"Error creating database: {e}")
