import mysql.connector
import google.generativeai as genai
from flask import Flask, request, jsonify
from flask_cors import CORS  # <--- IMPORT THIS

app = Flask(__name__)
CORS(app)  # <--- ENABLE THIS: Allows your PHP site to talk to Python

# --- CONFIGURATION ---
GOOGLE_API_KEY = "AIzaSyDuhizUjzHmIjgZzLaclnDAeqMMMh0ZlEw" # 🔴 PASTE KEY HERE 🔴
genai.configure(api_key=GOOGLE_API_KEY)
model = genai.GenerativeModel('gemini-2.5-flash')

# --- SCHEMA ---
DB_SCHEMA = """
Table: inventory
Columns: 
- id (INT)
- part_name (VARCHAR): Name of the item
- category (VARCHAR)
- supplier (VARCHAR)
- stock_level (INT)
- status (ENUM): 'active' or 'obsolete'
- is_deleted (TINYINT): 0 is visible, 1 is deleted
"""

def get_db_connection():
    return mysql.connector.connect(
        host="localhost",
        user="root",
        password="",
        database="inv_management_db"
    )

@app.route('/ask', methods=['POST'])
def ask_ai():
    try:
        data = request.json
        user_query = data.get('query')
        user_role = data.get('role', 'guest')

        # 1. AI Prompt
        prompt = f"""
        You are a SQL expert. Write a MySQL query for this schema:
        {DB_SCHEMA}
        
        Rules:
        1. Return ONLY the SQL query (plain text).
        2. Do NOT filter by status or is_deleted (backend handles this).
        3. Use SELECT statements only.
        
        User Question: {user_query}
        """

        # 2. Get SQL from Gemini
        response = model.generate_content(prompt)
        # REMOVE SEMICOLONS to prevent security bypass!
        generated_sql = response.text.replace('```sql', '').replace('```', '').replace(';', '').strip()
        print(f"🤖 AI Logic: {generated_sql}")

        # 3. Security Injection (RBAC)
        secure_sql = ""
        security_filter = ""

        if user_role == 'guest':
            # Guests see ONLY Active & Not Deleted
            security_filter = "status = 'active' AND is_deleted = 0"
        else:
            # Admins see everything (dummy true condition)
            security_filter = "1=1"

        if "WHERE" in generated_sql.upper():
            secure_sql = generated_sql + f" AND ({security_filter})"
        else:
            secure_sql = generated_sql + f" WHERE {security_filter}"

        # 4. Execute
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        cursor.execute(secure_sql)
        results = cursor.fetchall()
        cursor.close()
        conn.close()

        return jsonify({"answer": results, "sql_used": secure_sql})

    except Exception as e:
        print(f"Error: {e}")
        return jsonify({"error": str(e)})

if __name__ == '__main__':
    app.run(debug=True, port=5000)