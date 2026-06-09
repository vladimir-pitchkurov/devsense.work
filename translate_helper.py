import json
import os
import sys

filepath = 'resources/quizzes/php-basics-interview/de.json'

def main():
    if len(sys.argv) < 2:
        print("Usage: python translate_helper.py <json_string_or_file>")
        sys.exit(1)
        
    input_arg = sys.argv[1]
    
    if os.path.exists(input_arg):
        with open(input_arg, 'r', encoding='utf-8') as f:
            new_questions = json.load(f)
    else:
        new_questions = json.loads(input_arg)
        
    if os.path.exists(filepath):
        try:
            with open(filepath, 'r', encoding='utf-8') as f:
                data = json.load(f)
        except json.JSONDecodeError:
            data = []
    else:
        data = []
        
    data.extend(new_questions)
    
    with open(filepath, 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=2)
        
    print(f"Appended {len(new_questions)} questions. Total: {len(data)}")

if __name__ == '__main__':
    main()
