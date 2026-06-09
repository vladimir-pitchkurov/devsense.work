import json
import sys

def main():
    start = int(sys.argv[1]) if len(sys.argv) > 1 else 0
    end = int(sys.argv[2]) if len(sys.argv) > 2 else 20
    with open('resources/quizzes/javascript-advanced-interview/en.json', encoding='utf-8') as f:
        data = json.load(f)
    for i in range(start, min(end, len(data))):
        q = data[i]
        print(f"=== Q{i+1} ===")
        print("QUESTION:")
        print(q["question_text"])
        print("OPTIONS:")
        for opt in q["options"]:
            print(f" - {opt}")
        print("EXPLANATION:")
        print(q["explanation"])
        print()

if __name__ == '__main__':
    main()
