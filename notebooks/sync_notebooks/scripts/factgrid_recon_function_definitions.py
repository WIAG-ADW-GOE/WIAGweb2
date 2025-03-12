from tqdm import tqdm
import aiohttp
import asyncio
import time
import ssl
import copy
import pandas as pd
import traceback

missed = [] # entries for whom content could not be retrieved because of some error
entries_to_be_updated = [] # FactGrid-IDs which point to an outdated WIAG-ID (WIAG redirected to a newer one) and for which the new WIAG entry does not point to the FactGrid-ID
wiag_different_fgID = [] # WIAG-IDs that link to a different FG-ID from the one that points to them
wiag_missing_fgID = [] # WIAG-IDs to whom a FactGrid entry points, but which point to no FactGrid-ID

async def get(fg_wiag_id, fg_id, session):
    global missed
    
    wiag_id = None
    try:
        async with session.get(url=f'https://wiag-vocab.adw-goe.de/id/{fg_wiag_id}?format=Json') as response:
            data = await response.json()
            wiag_id = data['persons'][0]['wiagId']
            wiag_redirected = wiag_id != fg_wiag_id

            wiag_qid = data['persons'][0]['identifier']['Factgrid'].split('/')[-1]
            if wiag_qid != fg_id:
                wiag_different_fgID.append([fg_wiag_id, wiag_redirected, fg_id, wiag_qid])
            # elif wiag_redirected:
                # seems to be true only for entries with two entries in FactGrid, which link to two different WIAG-IDs, which are merged in WIAG (3 in total in 2025-03)
    except KeyError as e:
        assert(wiag_id != None)# if the entry is found, there must be a WIAG-ID and if not, it would most likely raise a ContentTypeError when the server responds "Kein Eintrag für ID {fg_wiag_id} vorhanden."

        if wiag_redirected: # updating FG entries when WIAG redirected to a newer entry and the new entry does not yet link to the FG entry (the WIAG-ID in FactGrid is outdated)
            entries_to_be_updated.append({
                "qid": fg_id,
                "-P601": fg_wiag_id,
                "P601": wiag_id,
            })
        else:
            wiag_missing_fgID.append([fg_wiag_id, fg_id])
    except aiohttp.client_exceptions.ContentTypeError as e:
        missed.append([fg_wiag_id, fg_id])
        if "503 Service Temporarily Unavailable" not in str(response) and "500 Internal Server Error" not in str(response):
            # 503 service error sometimes happens and is expected. 500 internal error is less common and but also happens on a regular basis.
            print(f"Unexpected ContentTypeError:\n{response}") # unexpected other errors are printed to output
    except Exception as e:
        print(f"There was an unexpected error retrieving info for WIAG-ID {fg_wiag_id}. The Exception message:\n{e.message}")
        print(f"And traceback:\n {traceback.format_exc()}")
        
# main executes the get function for the list of entries in batches
async def main(entries_to_be_checked):
    global missed
    number_of_entries_to_be_checked = len(entries_to_be_checked)
    
    missed = [] # resettting missed before each attempt

    # entries_to_be_checked is a list of a zip of two lists (pairing WIAG-ID and FactGrid-ID for each entry)
    async with aiohttp.ClientSession() as session:
        chunk_size = 1_000
        for i in range(0, number_of_entries_to_be_checked, chunk_size):
            try:
                # defining the batch and unpacking entry into WIAG-ID and FactGrid-ID
                _entries_to_be_checked_batch = (get(*entry, session) for entry in entries_to_be_checked[i : i + chunk_size])
                await asyncio.gather(*_entries_to_be_checked_batch) # concurrent execution of the get function for the batch
                if(i + chunk_size <= number_of_entries_to_be_checked):
                    print(f"{i + chunk_size}/{number_of_entries_to_be_checked} checked. Missed count: {len(missed)}")
                else:
                    print(f"{number_of_entries_to_be_checked}/{number_of_entries_to_be_checked} checked. Missed count: {len(missed)}")
                time.sleep(1)
            except ssl.SSLError as e:
                i = i - 1
                print(f"Retrying last batch")
    if(len(missed) > 0):
        print(f"Finalized all. Couldn't get data for {len(missed)} entries")
    else:
        print(f"Finalized all. Finished fetching data for all entries.")
    
    return missed







