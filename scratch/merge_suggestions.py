import os
import re

langs = ['bg', 'de', 'es', 'fr', 'it', 'ru', 'ua']

for lang in langs:
    filepath = f"lang/{lang}/ui.php"
    if not os.path.exists(filepath):
        print(f"Skipping {lang} (not found)")
        continue
        
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()
        
    # Let's find the first and second suggestions blocks
    # We can split the content by 'suggestions\' => ['
    parts = content.split("'suggestions' => [")
    if len(parts) < 3:
        print(f"Error: expected at least 2 suggestions blocks in {lang}, found {len(parts)-1}")
        continue
        
    # parts[0]: content before first block
    # parts[1]: content of first block + content between blocks
    # parts[2]: content of second block + content after second block
    
    # Let's extract the first block content
    # We find the matching closing bracket '],' at indentation 4 (since suggestions is indented by 4 spaces)
    # The first block starts with parts[1]
    first_block_raw = ""
    open_brackets = 1
    lines = parts[1].split('\n')
    idx = 0
    while idx < len(lines) and open_brackets > 0:
        line = lines[idx]
        if '[' in line:
            open_brackets += line.count('[')
        if ']' in line:
            open_brackets -= line.count(']')
        first_block_raw += line + "\n"
        idx += 1
        
    between_content = "\n".join(lines[idx:])
    
    # Let's extract the second block content
    second_block_raw = ""
    open_brackets = 1
    lines_sec = parts[2].split('\n')
    idx_sec = 0
    while idx_sec < len(lines_sec) and open_brackets > 0:
        line = lines_sec[idx_sec]
        if '[' in line:
            open_brackets += line.count('[')
        if ']' in line:
            open_brackets -= line.count(']')
        second_block_raw += line + "\n"
        idx_sec += 1
        
    after_content = "\n".join(lines_sec[idx_sec:])
    
    # Parse key-values from first block
    first_keys = {}
    # We want to extract key-value lines
    # For nested arrays like 'status' => [...], we want to extract it as a whole string
    # We can write a simple parser for keys in the block
    def parse_block(raw_text):
        keys = {}
        lines = raw_text.split('\n')
        i = 0
        while i < len(lines):
            line = lines[i].strip()
            if not line:
                i += 1
                continue
            # Match key
            m = re.match(r"['\"]([a-zA-Z0-9_\-]+)['\"]\s*=>\s*(.*)", line)
            if m:
                key = m.group(1)
                val_rest = m.group(2)
                # If it starts a nested array
                if val_rest.strip() == '[':
                    nested_raw = "[\n"
                    open_b = 1
                    i += 1
                    while i < len(lines) and open_b > 0:
                        nline = lines[i]
                        if '[' in nline:
                            open_b += nline.count('[')
                        if ']' in nline:
                            open_b -= nline.count(']')
                        nested_raw += nline + "\n"
                        i += 1
                    keys[key] = nested_raw.strip().rstrip(',')
                else:
                    keys[key] = val_rest.rstrip(',')
            else:
                i += 1
        return keys

    first_parsed = parse_block(first_block_raw)
    second_parsed = parse_block(second_block_raw)
    
    # Now let's merge them
    merged = {}
    # 1. First block keys
    for k, v in first_parsed.items():
        merged[k] = v
        
    # 2. Second block keys
    for k, v in second_parsed.items():
        if k == 'title':
            merged['global_title'] = v
        elif k == 'placeholder':
            merged['global_placeholder'] = v
        else:
            merged[k] = v
            
    # Reconstruct the suggestions block
    new_block = "    'suggestions' => [\n"
    for k, v in sorted(merged.items()):
        # Indent correctly
        if v.startswith('['):
            # Nested block
            lines = v.split('\n')
            new_block += f"        '{k}' => [\n"
            for nline in lines[1:]:
                new_block += f"        {nline}\n"
            new_block = new_block.rstrip('\n') + ",\n"
        else:
            new_block += f"        '{k}' => {v},\n"
    new_block += "    ],"
    
    # Let's put everything back together
    # content = parts[0] + new_block + between_content + (second block deleted) + after_content
    new_content = parts[0] + new_block + "\n" + between_content.strip() + "\n" + after_content.strip()
    
    # Normalize double newlines or trailing braces
    new_content = re.sub(r'\n\s*\n\s*\n', '\n\n', new_content)
    
    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(new_content)
        
    print(f"Successfully merged suggestions in {lang}")
