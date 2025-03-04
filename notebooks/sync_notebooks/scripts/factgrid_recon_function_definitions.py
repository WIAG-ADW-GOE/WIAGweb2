from tqdm import tqdm
import aiohttp
import asyncio
import time
import ssl
import copy
import pandas as pd

missed = [] # entries for whom content could not be retrieved because of some error
entries_to_be_updated = [] # FactGrid-IDs which point to an outdated WIAG-ID (WIAG redirected to a newer one) and for which the WIAG entry does not point to the FactGrid-ID
wiag_different_fgID = [] # WIAG-IDs, to whom a different FactGrid entry, from the one that they point to, points
wiag_missing_fgID = [] # WIAG-IDs to whom a FactGrid entry points, but which point to no FactGrid-ID

# making sure that in case something is wrong with the server and loads of type-500 errors pop up, it is not ignored
normal_500_error_count = 10
counter_500_error = 0

async def get(fg_wiag_id, fg_id, session):
    global missed
    global counter_500_error
    
    wiag_id = fg_wiag_id
    try:
        async with session.get(url=f'https://wiag-vocab.adw-goe.de/id/{fg_wiag_id}?format=Json') as response:
            data = await response.json()
            wiag_id = data['persons'][0]['wiagId']
            wiag_qid = data['persons'][0]['identifier']['Factgrid'].split('/')[-1]
            if wiag_qid != fg_id:
                wiag_different_fgID.append([fg_wiag_id, fg_id, wiag_qid])
    except KeyError as e:
        if wiag_id != fg_wiag_id: # adding new entries when there is no FactGrid-ID (qID) in WIAG and WIAG redirected to a newer entry (the WIAG-ID in FactGrid is outdated)
            entries_to_be_updated.append({
                "qid": fg_id,
                "-P601": fg_wiag_id,
                "P601": wiag_id,
            })
        else:
            wiag_missing_fgID.append([fg_wiag_id, fg_id])
    except aiohttp.client_exceptions.ContentTypeError as e:
        missed.append([fg_wiag_id, fg_id])
        if "503 Service Temporarily Unavailable" not in str(response): # 503 service error sometimes happens and is expected.
            if "500 Internal Server Error" not in str(response): # 500 internal error is less common and shouldn't occur too often, so the count of such errors is logged
                print(f"Unexpectd ContentTypeError:\n{response}") # unexpected other errors are printed to output
            else:
                counter_500_error += 1
    except Exception as e:
        print(f"There was an unexpected error retrieving info for WIAG-ID {fg_wiag_id}. The Exception message:\n{e.message}")
        print(f"And traceback:\n {traceback.format_exc()}")
        
# main executes the get function for the list of entries in batches
async def main(entries_to_be_checked):
    global missed
    
    missed = [] # resettting missed before each attempt

    # entries_to_be_checked is a list of a zip of two lists (pairing WIAG-ID and FactGrid-ID for each entry)
    async with aiohttp.ClientSession() as session:
        chunk_size = 1_000
        for i in range(0, len(entries_to_be_checked), chunk_size):
            try:
                # defining the batch and unpacking entry into WIAG-ID and FactGrid-ID
                _entries_to_be_checked_batch = (get(*entry, session) for entry in entries_to_be_checked[i : i + chunk_size])
                await asyncio.gather(*_entries_to_be_checked_batch) # concurrent execution of the get function for the batch
                print(f"{i + chunk_size}/{len(entries_to_be_checked)} checked. Missed count: {len(missed)}") # TODO improve the last output (e.g. 14000/13123 checked and 1000/4 checked)
                time.sleep(1)
            except ssl.SSLError as e:
                i = i - 1
                print(f"Retrying last batch")
    print(f"Finalized all. Couldn't get data for {len(missed)} entries")

    if counter_500_error > normal_500_error_count:
        print(f"{counter_500_error} errors of type 500 (internal server error). This is higher than expected, if this persists, maybe report the problem.")
    
    return missed







