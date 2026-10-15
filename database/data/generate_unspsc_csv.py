import zipfile
import xml.etree.ElementTree as ET
import csv
import time
import os

def main():
    t0 = time.time()
    xlsx_path = r"C:\Users\bryandellera\Downloads\unspsc_english.xlsx"
    out_csv = r"D:\zcmc_product_catalogue_server\database\data\unspsc_categories.csv"
    
    print(f"Reading {xlsx_path}...")
    with zipfile.ZipFile(xlsx_path, 'r') as z:
        shared_strings = []
        if 'xl/sharedStrings.xml' in z.namelist():
            print("Loading shared strings...")
            tree = ET.fromstring(z.read('xl/sharedStrings.xml'))
            ns = {'ns': 'http://schemas.openxmlformats.org/spreadsheetml/2006/main'}
            for si in tree.findall('.//ns:si', ns):
                t = si.find('.//ns:t', ns)
                shared_strings.append(t.text if t is not None and t.text is not None else '')
        
        print("Parsing sheet1.xml...")
        tree = ET.fromstring(z.read('xl/worksheets/sheet1.xml'))
        ns = {'ns': 'http://schemas.openxmlformats.org/spreadsheetml/2006/main'}
        rows = tree.findall('.//ns:row', ns)
        print(f"Total raw rows: {len(rows)}")

    # PASS 1: Index Segments, Families, Classes
    print("Pass 1: Indexing hierarchy nodes...")
    seg_map = {} # seg_code -> {'key': key, 'code': seg, 'title': title, 'def': def, 'path': path}
    fam_map = {} # (seg, fam) -> {'key': key, 'parent_key': seg_key, 'code': fam, 'title': title, 'def': def, 'path': path}
    cls_map = {} # (fam, cls) -> {'key': key, 'parent_key': fam_key, 'code': cls, 'title': title, 'def': def, 'path': path}
    cmd_rows = []

    # Process segments first
    for row in rows[13:]:
        vals = {}
        for c in row.findall('.//ns:c', ns):
            r_attr = c.get('r')
            col = ''.join([ch for ch in r_attr if ch.isalpha()])
            t_attr = c.get('t')
            v = c.find('ns:v', ns)
            val = v.text if v is not None else ''
            if t_attr == 's' and val != '':
                val = shared_strings[int(val)]
            vals[col] = val

        key = vals.get('B')
        seg = vals.get('C')
        fam = vals.get('F')
        cls = vals.get('I')
        cmd = vals.get('L')

        if not key or not seg:
            continue

        if not fam:
            # Segment (Level 0)
            seg_map[seg] = {
                'key': int(key),
                'parent_key': '',
                'code': seg,
                'title': vals.get('D', '').strip(),
                'def': vals.get('E', '').strip(),
                'level': 0,
                'path': f"/{key}/"
            }

    # Process families
    for row in rows[13:]:
        vals = {}
        for c in row.findall('.//ns:c', ns):
            r_attr = c.get('r')
            col = ''.join([ch for ch in r_attr if ch.isalpha()])
            t_attr = c.get('t')
            v = c.find('ns:v', ns)
            val = v.text if v is not None else ''
            if t_attr == 's' and val != '':
                val = shared_strings[int(val)]
            vals[col] = val

        key = vals.get('B')
        seg = vals.get('C')
        fam = vals.get('F')
        cls = vals.get('I')
        cmd = vals.get('L')

        if not key or not seg or not fam:
            continue

        if not cls or cls == fam:
            # Family (Level 1)
            seg_info = seg_map.get(seg)
            seg_key = seg_info['key'] if seg_info else ''
            fam_map[(seg, fam)] = {
                'key': int(key),
                'parent_key': seg_key,
                'code': fam,
                'title': vals.get('G', '').strip(),
                'def': vals.get('H', '').strip(),
                'level': 1,
                'path': f"/{seg_key}/{key}/" if seg_key else f"/{key}/"
            }

    # Process classes
    for row in rows[13:]:
        vals = {}
        for c in row.findall('.//ns:c', ns):
            r_attr = c.get('r')
            col = ''.join([ch for ch in r_attr if ch.isalpha()])
            t_attr = c.get('t')
            v = c.find('ns:v', ns)
            val = v.text if v is not None else ''
            if t_attr == 's' and val != '':
                val = shared_strings[int(val)]
            vals[col] = val

        key = vals.get('B')
        seg = vals.get('C')
        fam = vals.get('F')
        cls = vals.get('I')
        cmd = vals.get('L')

        if not key or not seg or not fam or not cls:
            continue

        if not cmd or cmd == cls:
            # Class (Level 2)
            fam_info = fam_map.get((seg, fam))
            fam_key = fam_info['key'] if fam_info else ''
            cls_path = f"{fam_info['path']}{key}/" if fam_info else f"/{key}/"
            cls_map[(fam, cls)] = {
                'key': int(key),
                'parent_key': fam_key,
                'code': cls,
                'title': vals.get('J', '').strip(),
                'def': vals.get('K', '').strip(),
                'level': 2,
                'path': cls_path
            }
        else:
            cmd_rows.append(vals)

    print(f"Indexed: Segments={len(seg_map)}, Families={len(fam_map)}, Classes={len(cls_map)}, Commodities={len(cmd_rows)}")

    # PASS 2: Write all rows to CSV in structured order (Segments, Families, Classes, Commodities)
    print("Writing output CSV...")
    os.makedirs(os.path.dirname(out_csv), exist_ok=True)
    seen_keys = set()
    written_count = 0

    with open(out_csv, 'w', newline='', encoding='utf-8') as f:
        writer = csv.writer(f)
        writer.writerow(['key', 'parent_key', 'code', 'title', 'level', 'path', 'definition'])

        # Write Segments
        for seg_code, s in seg_map.items():
            k = int(s['key'])
            if k not in seen_keys:
                seen_keys.add(k)
                writer.writerow([s['key'], s['parent_key'], s['code'], s['title'], s['level'], s['path'], s['def']])
                written_count += 1

        # Write Families
        for k_pair, fam in fam_map.items():
            k = int(fam['key'])
            if k not in seen_keys:
                seen_keys.add(k)
                writer.writerow([fam['key'], fam['parent_key'], fam['code'], fam['title'], fam['level'], fam['path'], fam['def']])
                written_count += 1

        # Write Classes
        for k_pair, cls in cls_map.items():
            k = int(cls['key'])
            if k not in seen_keys:
                seen_keys.add(k)
                writer.writerow([cls['key'], cls['parent_key'], cls['code'], cls['title'], cls['level'], cls['path'], cls['def']])
                written_count += 1

        # Write Commodities
        unresolved = 0
        for vals in cmd_rows:
            key_raw = vals.get('B')
            if not key_raw:
                continue
            k = int(key_raw)
            fam = vals.get('F')
            cls = vals.get('I')
            cmd = vals.get('L')
            cmd_title = vals.get('M', '').strip()
            cmd_def = vals.get('N', '').strip()

            if k in seen_keys:
                continue
            seen_keys.add(k)

            cls_info = cls_map.get((fam, cls))
            if not cls_info:
                unresolved += 1
                parent_key = ''
                cmd_path = f"/{k}/"
            else:
                parent_key = cls_info['key']
                cmd_path = f"{cls_info['path']}{k}/"

            writer.writerow([k, parent_key, cmd, cmd_title, 3, cmd_path, cmd_def])
            written_count += 1

    print(f"Finished writing {written_count} unique rows to {out_csv} in {time.time() - t0:.2f}s (Unresolved: {unresolved})")

if __name__ == '__main__':
    main()
