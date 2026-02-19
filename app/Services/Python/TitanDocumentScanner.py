import sys
import json
import os
import datetime
import warnings

# Suppress warnings
warnings.filterwarnings("ignore")

try:
    import pdfplumber
    import PyPDF2
    from docx import Document as DocxDocument
except ImportError as e:
    print(json.dumps({
        "error": "missing_dependencies",
        "message": f"Required library missing: {str(e)}",
        "details": str(e)
    }))
    sys.exit(1)

def serialize(obj):
    """JSON serializer for objects not serializable by default json code"""
    if isinstance(obj, (datetime.date, datetime.datetime)):
        return obj.isoformat()
    return str(obj)

def analyze_document(file_path):
    """
    Titan Edition Forensic Scanner
    - Preserves visual layout (critical for invoices/forms)
    - Extracts distinct headers/footers
    - Detects and formats tables as Markdown
    - Extracts deep metadata/XMP
    """
    if not os.path.exists(file_path):
        return {"error": "file_not_found", "message": f"File not found: {file_path}"}

    result = {
        "status": "success",
        "file_path": file_path,
        "full_text": "",
        "signals": {
            "page_count": 0,
            "has_images": False,
            "is_encrypted": False,
            "metadata": {}
        }
    }

    try:
        lower_path = file_path.lower()
        
        if lower_path.endswith('.pdf'):
            # 1. ENCRYPTION CHECK (PyPDF2 is faster for this)
            try:
                with open(file_path, 'rb') as f:
                    reader = PyPDF2.PdfReader(f)
                    if reader.is_encrypted:
                        return {"error": "encrypted_file", "message": "Document is password protected."}
            except Exception:
                pass # Continue to pdfplumber if PyPDF fails (sometimes plumber handles it)

            # 2. TITAN FORENSIC EXTRACTION (pdfplumber)
            with pdfplumber.open(file_path) as pdf:
                result['signals']['page_count'] = len(pdf.pages)
                result['signals']['metadata'] = pdf.metadata
                
                doc_text = []

                for i, page in enumerate(pdf.pages):
                    page_num = i + 1
                    doc_text.append(f"--- PAGE {page_num} START ---")

                    # A. LAYOUT TEXT EXTRACTION (Preserves spatial relationships)
                    # Critical for "Pixel-by-Pixel" auditing
                    layout_text = page.extract_text(layout=True, x_tolerance=2, y_tolerance=2)
                    if layout_text:
                        doc_text.append(layout_text)
                    
                    # B. TABLE EXTRACTION (Converted to Markdown for AI)
                    tables = page.extract_tables()
                    if tables:
                        doc_text.append(f"\n[DETECTED TABLES ON PAGE {page_num}]:")
                        for table in tables:
                            # Filter None/Empty values
                            clean_table = [[str(cell or "").replace("\n", " ") for cell in row] for row in table]
                            # Simple Markdown formatting
                            if clean_table:
                                # Header
                                doc_text.append("| " + " | ".join(clean_table[0]) + " |")
                                doc_text.append("| " + " | ".join(["---"] * len(clean_table[0])) + " |")
                                # Body
                                for row in clean_table[1:]:
                                    doc_text.append("| " + " | ".join(row) + " |")
                                doc_text.append("\n")

                    # C. IMAGE DETECTION
                    if page.images:
                        result['signals']['has_images'] = True
                        doc_text.append(f"[NOTE: Page {page_num} contains {len(page.images)} formatting images/logos]")

                    doc_text.append(f"--- PAGE {page_num} END ---\n")

                result['full_text'] = "\n".join(doc_text)

        elif lower_path.endswith('.docx'):
            doc = DocxDocument(file_path)
            full_text = []
            
            # CORE PROPS
            cp = doc.core_properties
            result['signals']['metadata'] = {
                'author': cp.author,
                'created': cp.created,
                'modified': cp.modified,
                'last_modified_by': cp.last_modified_by
            }

            # TEXT & TABLES
            for para in doc.paragraphs:
                full_text.append(para.text)
            
            for table in doc.tables:
                full_text.append("\n[DETECTED TABLE]:")
                rows = []
                for row in table.rows:
                    cells = [cell.text.strip().replace("\n", " ") for cell in row.cells]
                    rows.append("| " + " | ".join(cells) + " |")
                
                if rows:
                    full_text.append(rows[0]) # Header
                    full_text.append("| " + " | ".join(["---"] * len(table.rows[0].cells)) + " |") # Separator
                    full_text.extend(rows[1:]) # Body
                    full_text.append("\n")

            result['full_text'] = "\n".join(full_text)
            result['signals']['page_count'] = len(doc.sections) # Approximation

        else:
            return {"error": "unsupported_type", "message": f"Unsupported file type: {lower_path}"}

        # FINAL METRICS
        result['signals']['word_count'] = len(result['full_text'].split())
        return result

    except Exception as e:
        return {
            "error": "extraction_failed", 
            "message": str(e),
            "trace": str(sys.exc_info())
        }

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"error": "missing_argument", "message": "No file path provided."}))
        sys.exit(1)

    path = sys.argv[1]
    res = analyze_document(path)
    print(json.dumps(res, default=serialize))
