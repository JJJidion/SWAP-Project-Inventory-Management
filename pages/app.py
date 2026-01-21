import mysql.connector
import google.generativeai as genai
import re
import logging
from flask import Flask, request, jsonify
from flask_cors import CORS  # <--- IMPORT THIS

app = Flask(__name__)
CORS(app)  # <--- ENABLE THIS: Allows your PHP site to talk to Python

# --- CONFIGURATION ---
GOOGLE_API_KEY = "AIzaSyDuhizUjzHmIjgZzLaclnDAeqMMMh0ZlEw" # 🔴 PASTE KEY HERE 🔴
genai.configure(api_key=GOOGLE_API_KEY)
model = genai.GenerativeModel('gemini-2.5-flash-lite')

# Configure logging to write to a file
logging.basicConfig(
    filename='security_audit.log', 
    level=logging.WARNING,
    format='%(asctime)s - %(levelname)s - %(message)s'
)

# --- 1. THE TRUST LAYER (Input Scrubbing & Validation) ---
class TrustBoundary:
    """
    Acts as a firewall. No user input reaches the AI until it passes
    inspection here.
    """
    
    # Known adversarial phrases (Jailbreaks & Leaks)
    ADVERSARIAL_PATTERNS = [
        r"ignore.*previous",       # "Ignore previous instructions"
        r"system.*prompt",         # "Show me system prompt"
        r"act.*as",                # "Act as admin/developer"
        r"you.*are.*allowed",      # "You are allowed to..."
        r"bypass",                 # "Bypass security"
        r"delete.*table",          # SQL injection intent
        r"drop.*table",
        r"union.*select"
    ]

    # Business Logic Restrictions (Role-Specific)
    RESTRICTED_TERMS = {
        'employee': ['obsolete', 'hidden', 'deleted', 'margin', 'cost', 'salary', 'profit', 'revenue'],
        'admin': [] # Admins have no keyword restrictions
    }

    @staticmethod
    def sanitize_input(user_query):
        """
        Removes dangerous characters that could confuse the SQL generator
        or cause code injection.
        """
        # Allow only alphanumeric, spaces, and basic punctuation (?,.-)
        # This strips out weird symbols like ';', '--', '/*', etc.
        clean_query = re.sub(r"[^a-zA-Z0-9\s\?,\.\-]", "", user_query)
        return clean_query.strip()

    @staticmethod
    def validate_request(user_query, role, username):
        """
        Checks for adversarial attacks and role violations.
        Returns (True, None) if safe, or (False, ErrorMessage) if blocked.
        """
        lower_query = user_query.lower()

        # 1. Global Jailbreak Check (Regex)
        for pattern in TrustBoundary.ADVERSARIAL_PATTERNS:
            if re.search(pattern, lower_query):
                # LOGGING ADDED HERE #
                logging.warning(f"SECURITY ALERT: Adversarial pattern '{pattern}' detected in query: '{user_query}' by User '{username} ('Role: '{role}')")
                return False, "⛔ SECURITY ALERT: Adversarial prompt detected."

        # 2. Role-Based Keyword Check
        forbidden_words = TrustBoundary.RESTRICTED_TERMS.get(role, [])
        for word in forbidden_words:
             if word in lower_query:
                # LOGGING ADDED HERE #
                logging.warning(f"ACCESS DENIED: User '{username}' (Role '{role}') tried to access restricted term '{word}' in query: '{user_query}'")
                return False, "⛔ ACCESS DENIED: You are not permitted to view this information."

        return True, None

# --- 2. DATA RETRIEVAL LAYER (External RBAC) ---
class DataLayer:
    """
    Enforces permissions strictly via Code, ignoring whatever the AI says.
    """
    
    DB_CONFIG = {
        "host": "localhost",
        # 👇 CHANGED: No longer using root so its safer!
        "user": "ai_search_bot",
        "password": "StrongPassword123!",
        "database": "inv_management_db"
    }
    @staticmethod
    def get_unique_categories():
        """
        Fetches a live list of all unique categories currently in the DB.
        """
        try:
            conn = mysql.connector.connect(**DataLayer.DB_CONFIG)
            cursor = conn.cursor()
            # Get distinct categories, ignoring empty ones
            cursor.execute("SELECT DISTINCT category FROM inventory WHERE category IS NOT NULL AND category != ''")
            categories = [row[0] for row in cursor.fetchall()]
            cursor.close()
            conn.close()
            return categories
        except Exception:
            return [] # Fallback to empty list if DB fails
        
    @staticmethod
    def get_unique_suppliers():
        """
        Fetches a live list of all unique suppliers currently in the DB.
        """
        try:
            conn = mysql.connector.connect(**DataLayer.DB_CONFIG)
            cursor = conn.cursor()
            # Get distinct supplier, ignoring empty ones
            cursor.execute("SELECT DISTINCT supplier FROM inventory WHERE supplier IS NOT NULL AND supplier != ''")
            supplier = [row[0] for row in cursor.fetchall()]
            cursor.close()
            conn.close()
            return supplier
        except Exception:
            return [] # Fallback to empty list if DB fails
        
    @staticmethod
    def get_rbac_policy(role):
        """
        Returns the mandatory SQL suffix for the user's role.
        The AI cannot override this.
        """
        if role == 'employee':
            # STIRCT: Only active, not deleted, and exclude financial columns if possible
            return "status = 'active' AND is_deleted = 0"
        elif role == 'admin':
            return "1=1" # Admins see all
        else:
            return "1=0" # Unknown roles see NOTHING

    @staticmethod
    def execute_secure_search(ai_generated_sql, role):
        """
        Takes the AI's 'logic' and wraps it in our 'security rules'.
        """
        try:
            # 1. Force the RBAC Filter
            rbac_filter = DataLayer.get_rbac_policy(role)
            
            # 2. Rewrite the query to enforce the filter
            # We assume the AI returns a SELECT ... statement.
            # We append the WHERE clause safely.
            
            clean_sql = ai_generated_sql.replace(';', '') # Double check
            
            if "WHERE" in clean_sql.upper():
                secure_sql = f"{clean_sql} AND ({rbac_filter})"
            else:
                secure_sql = f"{clean_sql} WHERE {rbac_filter}"

            # 3. Connect and Execute
            conn = mysql.connector.connect(**DataLayer.DB_CONFIG)
            cursor = conn.cursor(dictionary=True)
            cursor.execute(secure_sql)
            results = cursor.fetchall()
            cursor.close()
            conn.close()
            
            return results, secure_sql

        except Exception as e:
            return None, str(e)

# --- 3. MAIN APP LOGIC ---

DB_SCHEMA_PROMPT = """
Table: inventory
Columns: 
- id (INT)
- part_name (VARCHAR)
- category (VARCHAR)
- stock_level (INT)
- status (ENUM): 'active', 'obsolete'
- is_deleted (TINYINT)
"""

@app.route('/ask', methods=['POST'])
def ask_ai():
    try:
        data = request.json
        raw_query = data.get('query', '')
        user_role = data.get('role', 'employee')
        username = data.get('username', 'Unknown_User') # <--- Get the username (default to Unknown)

        # --- STEP 1: TRUST LAYER ---
        clean_query = TrustBoundary.sanitize_input(raw_query)
        is_safe, error_msg = TrustBoundary.validate_request(clean_query, user_role, username)
        if not is_safe:
            return jsonify({"answer": error_msg, "sql_used": "Blocked by Trust Layer"})

        # --- STEP 1.5: DYNAMIC CONTEXT FETCHING ---
        # Fetch the live categories from the database
        live_categories = DataLayer.get_unique_categories()
        # Format them like: 'Cables', 'Connectors', 'Tools'
        categories_str = ", ".join([f"'{c}'" for c in live_categories])

        live_suppliers = DataLayer.get_unique_suppliers()
        sup_str = ", ".join([f"'{s}'" for s in live_suppliers])

        # --- STEP 2: AI PROCESSING ---
        # We construct the prompt dynamically now!
        prompt = f"""
        You are a MySQL generator. 
        
        Table: inventory
        Columns: 
        - id (INT)
        - part_name (VARCHAR)
        - category (VARCHAR). Valid values found in DB: [{categories_str}]
        - supplier (VARCHAR). Valid values: [{sup_str}]  
        - stock_level (INT)
        - status (ENUM): 'active', 'obsolete'
        - is_deleted (TINYINT)

        Task: Write a SQL query for: "{clean_query}"
        
        Rules: 
        1. Return ONLY SQL. No Markdown. No Semicolons.
        2. ALWAYS use 'SELECT *' (Select All) so the frontend has all data columns.
        3. If the user searches for a category/supplier not in the list, match the closest valid value.
        """
        
        response = model.generate_content(prompt)
        ai_sql = response.text.replace('```sql', '').replace('```', '').strip()

        # --- STEP 3: DATA RETRIEVAL LAYER ---
        results, final_sql = DataLayer.execute_secure_search(ai_sql, user_role)

        if results is None:
             return jsonify({"error": final_sql}) 

        if not results:
            return jsonify({"answer": "No matching records found.", "sql_used": final_sql})

        return jsonify({"answer": results, "sql_used": final_sql})

    except Exception as e:
        print(f"System Error: {e}")
        return jsonify({"error": "Internal Server Error"})
    
if __name__ == '__main__':
    app.run(debug=False, port=5000)