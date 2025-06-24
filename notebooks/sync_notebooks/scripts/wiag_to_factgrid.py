# %% [markdown]
## 6. wiag_to_factgrid.ipynb
# 
### **Office Creation Notebook**
#This notebook takes data from wiag as the primary source, then joins it with
#* institution data from factgrid
#* diocese data from factgrid
#* role data from wiag
#* role data from factgrid
# 
#and then creates a final quickstatements csv at the end to be uploaded to FactGrid.
# 
#At every join operation, there is the possibility that some data in wiag has no corresponding data in factgrid.
#The notebook create a quickstatements csv to create the missing data whenever this happens.
#After creating the csv, it removes all the missing entries and moves on the next step (these cells are marked with two stars **). There are two possible routes to execute this notebook:
#1. [Import the csv files to factgrid](https://database.factgrid.de/quickstatements/#/batch) whenever one is generated, and then run the notebook from the beginning up to that point. 
#2. Do not create any factgrid entries except at the very last step. This flow works since all missing entries are ignored after their corresponding csv file is generated.
# 
#This is explained with the diagram below. The description of the shapes is below:
#* Diamonds: The diamonds indicate a join operation. After this operation you have entries that have been successfully added information to.
#* Circles: The circles indicate the records that were successfully joined. This means that there was more information added to the orignal record.
#* Rectangle: The rectanges indicate the records that failed the join operation.
# 
#![office_creation_flow.drawio.png](docs/images/office_creation_flow.drawio.png)
# 
#The large arrow on the right that goes back up indicates that after a join operation is failed, you could use the generated files to fix the problems with the records and start from the top again.
# 
#So the first route to execute this notebook is to follow the square blocks as soon as you encounter one and then take the arrow back to the start. The second route is to follow the circle blocks and go down until the final csv file is generated.

# %% [markdown]
### 1. Setup
# 

# %%
import requests
import csv
import os
import json
import re
import time
from datetime import datetime, timedelta
import math
import traceback
import polars as pl
import polars.selectors as cs

# %%
input_path = r"C:\Users\Public\WIAGweb2\notebooks\sync_notebooks\input_files"

# %%
output_path = r"C:\Users\Public\WIAGweb2\notebooks\sync_notebooks\output_files"

# %%
today_string = datetime.now().strftime('%Y-%m-%d')

# %% [markdown]
### 2. Download data from WIAG
#### Export data from WIAG database
# 
#For this step you need to manually export the dataset by opening [phpMyAdmin (WIAG)](https://vwebfile.gwdg.de/phpmyadmin/) and then:
#1. choose the wiagvokabulare database
#2. run the saved 'Step 6 of the sync notebooks for WIAG' sql query
#3. export the result to a csv file
#  
#A detailed description can be found here: [Run_SQL_Query_and_Export_CSV.md](https://github.com/WIAG-ADW-GOE/WIAGweb2/blob/main/notebooks/sync_notebooks/docs/Run_SQL_Query_and_Export_CSV.md) (As a backup, [here is the saved query](https://github.com/WIAG-ADW-GOE/WIAGweb2/blob/main/notebooks/sync_notebooks/scripts/get_wiag_roles.sql))
# 
# 
#### Import the files
#Please move the downloaded file to the `input_path` directory defined above or change the `input_path` to where the file is located.

# %%
input_file = f'role.csv'
input_path_file = os.path.join(input_path, input_file)
wiag_roles_df = pl.read_csv(input_path_file, null_values='NULL', columns = [0, 2, 17], new_columns=['id', 'name', 'role_fg_id'])
len(wiag_roles_df)

# %% [markdown]
#### Download data from WIAG
#https://wiag-vokabulare.uni-goettingen.de/query/can
# 
#It's recommended to limit the export to one Domstift by first searching for that Domstift before exporting the 'CSV Amtsdaten' to make sure that the amount of roles to be added is manageable.
#
#If you filtered by Domstift (cathedral chapter), **change the variable below** to the domstift you used and **change the name of the exported file** to include the name of the cathedral chapter.
#
#
#If you did not filter, you need to change the line to `domstift = ""`.

# %%
domstift = "Mainz" # with domstift = "Mainz" the name of the file should be "WIAG-Domherren-DB-Ämter-Mainz.csv"
# domstift = "" # in case you did not filter by Domstift, use this instead

# %%
input_file = f'WIAG-Domherren-DB-Ämter-' + domstift + '.csv'
input_path_file = os.path.join(input_path, input_file)
role_all_df = pl.read_csv(input_path_file, separator=';', infer_schema_length = None)
len(role_all_df)

# %%
last_modified = datetime.fromtimestamp(os.path.getmtime(input_path_file))
now = datetime.now()
assert last_modified.day == now.day and last_modified.month == now.month, f'The file was last updated on {last_modified.strftime('%d.%m')}'

# %% [markdown]
##### Troubleshooting: Old file used
#You get an error when you run the line above if the file was not updated today.
#Suggested solutions: 
#* update the file again by downloading it again
#* if you downloaded the data today, check the file name in input_file. It's pointing to a file that has old data.
#* (not recommended) continue if you are sure that you need to use old data. This is something that the developer might want to do.

# %% [markdown]
### 3. Download data from factgrid
# 
#Troubleshooting: If any of the following requests to factgrid fail, try rerunning the cells.

# %% [markdown]
#The following cell looks up institutions with an entry in the 'Klosterdatenbank' and gets their id.

# %%
url = 'https://database.factgrid.de/sparql'
query = (
    """SELECT ?item ?gsn WHERE {
  ?item wdt:P471 ?gsn
}
"""
)
#SERVICE wikibase:label { bd:serviceParam wikibase:language "[AUTO_LANGUAGE],en". }

#make request: 
r = requests.get(url, params={'query': query}, headers={"Accept": "application/json"})
data = r.json()
factgrid_institution_df = pl.json_normalize(data['results']['bindings'])
factgrid_institution_df = factgrid_institution_df.cast({'gsn.value':pl.UInt32})

len(factgrid_institution_df)

# %% [markdown]
#The following cell looks up

# %%
url = 'https://database.factgrid.de/sparql'
query = (
"""
SELECT DISTINCT ?item ?wiagid ?label ?alternative WHERE {
  ?item wdt:P2/wdt:P3* wd:Q164535.
  #?item schema:description ?itemDesc.
  ?item rdfs:label ?label.
  OPTIONAL {?item schema:description ?itemDesc.}
  OPTIONAL {?item skos:altLabel ?alternative. }
  OPTIONAL {?item wdt:P601 ?wiagid.}
  FILTER(LANG(?label) in ("en", "de"))
}
"""
)

#make request: 
r = requests.get(url, params={'query': query}, headers={"Accept": "application/json"})
data = r.json()
factgrid_diocese_df = pl.json_normalize(data['results']['bindings'])

len(factgrid_diocese_df)

# %%
url = 'https://database.factgrid.de/sparql'
query = (
"""
SELECT ?item ?label WHERE {
  ?item wdt:P2 wd:Q257052.
  ?item rdfs:label ?label.
  FILTER(LANG(?label) in ("de"))
}
"""
)

r = requests.get(url, params={'query': query}, headers={"Accept": "application/json"})
data = r.json()
factgrid_inst_roles_df = pl.json_normalize(data['results']['bindings'])

len(factgrid_inst_roles_df)

# %% [markdown]
#### Clean Factgrid data

# %%
#extract out q id
def extract_id(df, column):
    return df.with_columns(pl.col(column).str.strip_chars('https://database.factgrid.de/entity/'))

#drop irrelevant columns
def drop_type_columns(df):
    df = df.drop(
        cs.ends_with("type"),
        cs.ends_with("xml:lang")
        )
    return df

# %%
factgrid_institution_df = extract_id(factgrid_institution_df, 'item.value')
factgrid_diocese_df = extract_id(factgrid_diocese_df, 'item.value')
factgrid_inst_roles_df = extract_id(factgrid_inst_roles_df, 'item.value')

# %%
factgrid_institution_df = drop_type_columns(factgrid_institution_df)
factgrid_diocese_df = drop_type_columns(factgrid_diocese_df)
factgrid_inst_roles_df = drop_type_columns(factgrid_inst_roles_df)

# %%
#rename columns
factgrid_institution_df.columns = ['fg_institution_id', 'fg_gsn_id']
factgrid_diocese_df.columns = ["fg_diocese_id", "dioc_label", "dioc_alt", "dioc_wiag_id"]
factgrid_inst_roles_df.columns = ["fg_inst_role_id", "inst_role"]

# %%
#clean the diocese alts by removing BITECA and BETA entries 
factgrid_diocese_df = factgrid_diocese_df.with_columns(pl.col('dioc_alt').str.replace('^(BITECA|BETA).*', ''))

# %%
duplicate_fg_entries = factgrid_institution_df.group_by('fg_gsn_id').len().filter(pl.col('len') > 1)
if not duplicate_fg_entries.is_empty():
    print(duplicate_fg_entries)
    raise f"There are possible institution duplicates on factgrid."

# %% [markdown]
##### Troubleshooting: possible institution duplicates
#This can be caused by a simple human error on factgrid. 
#The best solution is to use the factgrid ids printed above and resolve the duplicates.
# 
#In case you want to ignore the duplicates, uncomment the code below by removing the leading '# ' (keyboard shortcut 'ctrl + /') and run it.

# %%
#factgrid_institution_df = factgrid_institution_df.filter(pl.col('fg_gsn_id').is_in(duplicate_fg_entries.select('fg_gsn_id').not_()))

# %% [markdown]
### 4. Join person role data from WIAG with institution and diocese data from Factgrid

# %% [markdown]
#First the WIAG "Amtsdaten" for Domherren export is joined with institution data from FactGrid

# %%
role_inst_df = role_all_df.join(factgrid_institution_df, how='left', left_on='institution_id', right_on='fg_gsn_id')

# %% [markdown]
#Next the diocese data is added.
# 
#For each entry in the input dataframe, the associated diocese is searched in the factgrid_diocese_df dataframe. The diocese is found by first searching for the WIAG-ID. Only if no entry was found, the search continues with the diocese's name, first in the diocese label and lastly, if the search was unsuccessfull again, in the diocese alt label.

# %%
# join with fg dioceses
rows = []
query = pl.DataFrame() # empty initialisation to enable the call of the clear function below

for row in role_inst_df.iter_rows(named = True):
    query = query.clear()

    if row['diocese_id'] != None:
        query = factgrid_diocese_df.filter(pl.col('dioc_wiag_id') == row['diocese_id'])
        
    if query.is_empty() and row['diocese'] != None:
        query = factgrid_diocese_df.filter(pl.col('dioc_label') == row['diocese'])
        
        if query.is_empty():
            query = factgrid_diocese_df.filter(pl.col('dioc_alt') == row['diocese'])

    if not query.is_empty():
        rows.append({'role_all-id': row['id'], 'fg_diocese_id': query.row(0)[0]})
    # #TODO should cases where no result was found be noted/handled?

role_inst_dioc_df = role_inst_df.join(pl.DataFrame(rows), how = 'left', left_on = 'id', right_on = 'role_all-id')

# %% [markdown]
### 5. Check for special cases

# %% [markdown]
#These lists below allow the code below to identify if the role is missing an institution or if the role doesn't require one at all.
# 
#* The `unbound_role_groups` list contains the role_groups that are not bound to a place at all.
# 
#* The `diocese_role_groups` list contains the role_groups that are bound to a diocese but not an institution.
#  * `diocese_role_group_exception_roles` contains roles that belong to this group but are still bound to an institution.
# 
#Please add more role_groups or roles to the lists if necessary.

# %%
unbound_role_groups = [
    'Kurienamt',
    'Papst',
    'Kardinal',
]
diocese_role_groups = [
    'Oberstes Leitungsamt Diözese',
    'Leitungsamt Diözese',
    'Bischöfliches Hilfspersonal',
]
diocese_role_group_exception_roles = [
    None,
]

# %%
# select all entries that should contain an institution on factgrid but don't have it after the join operation
missing_inst_df = role_inst_dioc_df.filter(
    pl.col('fg_institution_id').is_null() &
    pl.col('role_group').is_in(unbound_role_groups).not_() &
    pl.col('role_group').is_in(diocese_role_groups).not_()
)
print(str(missing_inst_df.height) + " entries with missing institution id in FG")

# select all entries that should contain a diocese on factgrid but don't have it after the join operation
missing_dioc_df = role_inst_dioc_df.filter(
    pl.col('fg_diocese_id').is_null() & 
    pl.col('role_group').is_in(unbound_role_groups).not_() & 
    pl.col('role_group').is_in(diocese_role_groups) &
    pl.col('name').is_in(diocese_role_group_exception_roles).not_()
)
print(str(missing_dioc_df.height) + " entries with missing diocese id in FG")

# %% [markdown]
#### Check for new roles (roles that so far have not been handled by this notebook)
#Any roles showing up here need to be added to either the `diocese_role_group_exception_roles` list if they don't need a diocese entry in FactGrid or to the `roles_that_need_a_diocese` list (defined below) if they do need a diocese entry. If you added a name to the `diocese_role_group_exception_roles` list, rerun the cells from the start of step 5 to make sure the change is propagated.

# %%
roles_that_need_a_diocese = ['Bischof', 'Koadjutor', 'Erzbischof']
missing_dioc_df.filter(pl.col('name').is_in(roles_that_need_a_diocese).not_())

# %% [markdown]
#### Check entries that have no role group in wiag

# %%
missing_inst_df.filter(pl.col('role_group').is_null())

# %% [markdown]
#### Check for entries that are missing an id required for the join
#Please **manually inspect all the entries** that are shown by the code cells below

# %% [markdown]
##### Entries that have a missing institution id in WIAG

# %%
missing_inst_df.filter(pl.col('institution_id').is_null())

# %% [markdown]
##### Entries that have a missing diocese id in WIAG

# %%
missing_dioc_df.filter(pl.col('diocese_id').is_null())

# %% [markdown]
### Create the missing institutions on factgrid here

# %% [markdown]
#Creates a file with the name institution_creation_\<date\>.csv
# 
#**You need to fill in the empty columns of the file** (except qid) and then use the file on quickstatements.

# %%
create_institution_factgrid_df = missing_inst_df.filter(pl.col('institution_id').is_not_null()).rename({'institution' : 'Lde', 'institution_id' : 'P471'}).unique(subset = pl.col('P471')).with_columns(
    qid = None,
    Len = None,
    Lfr = None,
    Les = None,
    Dde = None,
    Den = None,
    P131 = pl.lit('Q153178')
).select(['qid', 'Lde', 'Len',	'Lfr',	'Les',	'P471',	'Dde',	'Den',	'P131'])

create_institution_factgrid_df.write_csv(file = os.path.join(output_path, f'institution_creation_{today_string}.csv'), separator = ';')

create_institution_factgrid_df.sample(n = 5)

# %% [markdown]
#### Remove all missing (institution and diocese) entries **

# %%
all_missing_entries = pl.concat([missing_inst_df, missing_dioc_df], how = "diagonal")

dioc_joined_df = role_inst_dioc_df.remove(pl.col("id").is_in(all_missing_entries.get_column("id")))

print("From originally " + str(role_inst_dioc_df.height) + " rows, " + str(dioc_joined_df.height) + " rows, that are not missing an institution or diocese, are left.")

# %% [markdown]
### Add role factgrid id
#Note: This role does not include the institution information. ie, it adds factgrid ids for roles like 'archbishop' and not 'archbishop of trier'
# 
#The part of the script below could be used to create quickstatements for career statements.

# %% [markdown]
##### Check for roles with multiple entries in FactGrid
#Should the cell below print anything, these entries need to be **handled manually**, because they contain more than one entry on FactGrid. You can continue with the rest of the notebook even without taking care of these, because these entries will simply be ignored.

# %%
wiag_roles_df.filter(pl.col("name").is_duplicated())

# %% [markdown]
#### Check for missing roles in WIAG role table

# %%
missing_roles_wiag = dioc_joined_df.filter(pl.col("name").is_in(wiag_roles_df.get_column("name")).not_()).unique()
print(missing_roles_wiag.height)
missing_roles_wiag.head()

# %% [markdown]
#### Join role_fg_id attribute from WIAG

# %%
wiag_roles_df = wiag_roles_df.remove(pl.col("name").is_duplicated())

joined_df = dioc_joined_df.join(wiag_roles_df.rename({'id' : 'role_id'}), on = "name", how = "left")

# %% [markdown]
##### Ignore all Kanonikatsbewerber and Vikariatsbewerber offices
#TODO: add reason here

# %%
joined_df = joined_df.remove(pl.col('name').is_in(['Vikariatsbewerber', 'Kanonikatsbewerber'])) # TODO why?

# %% [markdown]
#### Entries with missing factgrid entries for the roles in wiag

# %%
missing_roles_df = joined_df.filter(pl.col('role_fg_id').is_null())
print(str(missing_roles_df.height) + " entries are missing a role in FactGrid.\n")

print("Roles that are not yet in FactGrid:")
missing_roles = missing_roles_df.select(pl.col('name'), pl.col('role_id'), pl.col('role_group_fq_id')).unique().drop_nulls() # TODO report null values, instead of just dropping them
missing_roles

# %% [markdown]
##### Create a csv file to be manually filled and later read to generate quickstatements
# 
#Make changes to this file and then upload it to quickstatements (don't forget to remove the item_id column).

# %%
create_missing_roles_df = missing_roles.with_columns(
    qid = None,
    Len = None,
    Lfr = None,
    Les = None,
    Dde = None,
    Den = None,
    P2 = pl.lit("Q37073"),
    P131 = pl.lit("Q153178")
).rename({
    "name" : "Lde",
    "role_id" : "item_id",
    "role_group_fq_id" : "P3"}
).select(
    ["qid",	"Lde",	"Len",	"Lfr",	"Dde",	"Den",	"P2",	"P131",	"item_id",	"P3"]
)

create_missing_roles_df.write_csv(os.path.join(output_path, f"create-missing-roles-{today_string}.csv"), separator = ';')
create_missing_roles_df

# %% [markdown]
#### Remove all missing (role) entries now **
#The code below removes all the entries that failed the join with the wiag role join above.

# %%
with_roles_in_fg_df = joined_df.remove(pl.col('role_fg_id').is_null())

# %% [markdown]
#### Check people with missing factgrid entries or missing factgrid ids in wiag

# %%
missing_people_list = joined_df.filter(pl.col('FactGrid').is_null()).unique('person_id')
print(missing_people_list.height)
missing_people_list.sample(n = 3)

# %% [markdown]
#### Generate the quickstatements for creating the persons
# 
#Go back to step 5 (Csv2FactGrid-create) to create the missing persons.

# %% [markdown]
#### Remove all missing (person) entries now **
#The code below removes all the entries for persons that don't exist on factgrid

# %%
print(len(joined_df))
joined_df = joined_df.filter(pl.col('FactGrid').is_not_null())
print(len(joined_df))

# %% [markdown]
#### Add factgrid ids for roles (with institution)
#Note: this role has information of the institution as well

# %%
# in addition to the parameters, uses the dataframe factgrid_inst_roles_df directly

def find_fg_inst_role(name, inst, dioc):
    search_result = pl.DataFrame()
    if inst == None:
        if dioc != None: # TODO handle cases where inst and dioc are None? - should only be true for [35, 48, 49] Kardinal, Papst, Kurienamt (except maybe special role_groups)
            if name not in ["Archidiakon", "Koadjutor"]:
                dioc = dioc.lstrip('Bistum').lstrip('Erzbistum').lstrip('Patriarchat').lstrip()
            if name == "Fürstbischof" and dioc in ["Passau", "Straßburg"]:
                name = "Bischof"    
            search_result = factgrid_inst_roles_df.filter(pl.col('inst_role').str.contains(f"^{name}.*{dioc}"))
            if name == "Erzbischof" and dioc == "Salzburg":
                # will be merged in later # TODO what does this mean and why?
                search_result = factgrid_inst_roles_df.filter(pl.col('fg_inst_role_id') == 'Q172567')
    else:
        name = name.replace('Domkanoniker', 'Domherr')
        search_result = factgrid_inst_roles_df.filter(pl.col('inst_role') == f"{name} {inst}")
    
    return search_result

# %%
data_dict = [] # joined to the main df as fg_inst_role_id (used in the last part) - in other words, these are the institution roles that are assigned on FactGrid
not_found = [] # used for creating institution roles (e.g. bishop of ...) in the next cell
dupl = {} # these entries are ignored, because they need to be fixed manually

i = 0
for (id, name, inst, inst_id, dioc) in joined_df.select('id', 'name', 'institution', 'institution_id', 'diocese').iter_rows():
    # Kardinal receives insitution role Q254893 manually -- probably simply handling a simple special case first
    if name == "Kardinal":
        data_dict.append((id,"Q254893"))
        continue
    
    search_result = find_fg_inst_role(name, inst, dioc)

    if search_result.is_empty() or len(search_result) == 0:
        # TODO entries without institution entry in WIAG are simply ignored - makes sense if dioc is set?? (diocese level roles)
        not_found.append((name, inst, inst_id))
    elif len(search_result) == 1:
        data_dict.append((id, search_result['fg_inst_role_id'][0]))
    elif len(search_result) >= 2:
        dupl[i] = (name, inst, dioc, search_result)
    
    i += 1

print("Roles found:", len(data_dict), "duplicates:", len(dupl), "not found:", len(not_found))

# %% [markdown]
##### Create entries for missing inst roles on factgrid

# %%
not_found_df = pl.DataFrame(not_found, orient = 'row', schema = ['role', 'institution', 'institution_id'])
not_found_df = not_found_df.drop_nulls() # remove entries for diocese level roles 

# not_found contains an entry per row where a combination was not found - here we want just one row per unique combination
# these combinations could be found much more efficiently, but as it's a byproduct of finding the fg_inst_role_id for all the other rows, this is fine
not_found_df = not_found_df.unique()
# since the institution names are quite specific, it's not realistic that two roles with the same label but different institution_id could exist

# add role details
not_found_df = not_found_df.join(
    wiag_roles_df.select('id', 'name', 'role_fg_id'), how='left', left_on='role', right_on='name'
)
# add instution details
not_found_df = not_found_df.join(factgrid_institution_df, how='left', left_on='institution_id', right_on='fg_gsn_id')

# add other columns
not_found_df = not_found_df.with_columns(
    qid = None,
    Lde = pl.col('role') + ' ' + pl.col('institution'),
    Len = None,
    Lfr = None,
    Les = None,
    Dde = None,
    Den = None,
    P2 = pl.lit('Q257052'),
    P131 = pl.lit('Q153178'),
    P3 = pl.col('role_fg_id'),
    P267 = pl.col('fg_institution_id'),
    # id is the number of the role in the role table in wiag -- institution_id is the klosterdatenbank id of the institution
    P1100 = pl.when(pl.col('id').is_null()).then(pl.lit(None)).otherwise('off' + pl.col('id').cast(str) + '_gsn' + pl.col('institution_id').cast(str))
).select(['qid', 'Lde', 'Len', 'Dde', 'Den', 'P2', 'P131', 'P3', 'P267', 'P1100']) # selecting only relevant columns

# export to csv file
not_found_df.write_csv(os.path.join(output_path, f"create-missing-inst-roles-{today_string}.csv"), separator=';')
print(f'{not_found_df.height} rows were written. A sample of them:')
not_found_df.sample(n = 3)

# %% [markdown]
#### Ignore all missing (inst role) entries now **
#The code below ignores entries that are generated above and does a join without them.

# %%
final_joined_df = joined_df.join(pl.DataFrame(data_dict, schema = ['id', 'fg_inst_role_id'], orient = 'row'), on = 'id')
print(len(final_joined_df))
final_joined_df.sample(n = 3)

# %% [markdown]
#### Parse begin and end date from the wiag data
# 
#The following code parses the date information present in the date_begin or date_end string and converts it to the correct property in factgrid and it's corresponding value.
#There are also testcases which are run in case you want to modify it.
# 
#Here is an overview of relevant FactGrid properties: [link](https://database.factgrid.de/query/embed.html#SELECT%20%3FPropertyLabel%20%3FProperty%20%3FPropertyDescription%20%3Freciprocal%20%3FreciprocalLabel%20%3Fexample%20%3Fuseful_statements%20%3Fwd%20WHERE%20%7B%0A%20%20SERVICE%20wikibase%3Alabel%20%7B%20bd%3AserviceParam%20wikibase%3Alanguage%20%22en%22.%20%7D%0A%20%20%3FProperty%20wdt%3AP8%20wd%3AQ77483.%0A%20%20OPTIONAL%20%7B%20%3FProperty%20wdt%3AP364%20%3Fexample.%20%7D%0A%20%20OPTIONAL%20%7B%20%3FProperty%20wdt%3AP86%20%3Freciprocal.%20%7D%0A%20%20OPTIONAL%20%7B%20%3FProperty%20wdt%3AP343%20%3Fwd.%20%7D%0A%20%20OPTIONAL%20%7B%20%3FProperty%20wdt%3AP310%20%3Fuseful_statements.%20%7D%0A%7D%0AORDER%20BY%20%3FPropertyLabel)

# %%
# defining an enum to more clearly define what type of date is being passed 
from enum import Enum
class DateType(Enum):
    ONLY_DATE = 0
    BEGIN_DATE = 1
    END_DATE = 2

PRECISION_CENTURY = 7
PRECISION_DECADE = 8
PRECISION_YEAR = 9

# defining some constants for better readability of the code:
# self defined:
JULIAN_ENDING = '/J'
JHS_GROUP = r'(Jhs\.|Jahrhunderts?)'
JH_GROUP = r'(Jh\.|Jahrhundert)'
EIGTH_OF_A_CENTURY = 13
QUARTER_OF_A_CENTURY = 25
TENTH_OF_A_CENTURY = 10

ANTE_GROUP = "bis|vor|spätestens"
POST_GROUP = "nach|frühestens|ab"
CIRCA_GROUP = r"etwa|ca\.|um"
# pre-compiling the most complex pattern to increase efficiency
MOST_COMPLEX_PATTERN = re.compile(r'(wohl )?((kurz )?(' + ANTE_GROUP + '|' + POST_GROUP + r') )?((' + CIRCA_GROUP +r') )?(\d{3,4})(\?)?')

# FactGrid properties:
    # simple date properties:
DATE = 'P106' 
BEGIN_DATE = 'P49'
END_DATE = 'P50'
    # when there is uncertainty / when all we know is the latest/earliest possible date:
DATE_AFTER = 'P41' # the earliest possible date for something
DATE_BEFORE = 'P43' # the latest possible date for something
END_TERMINUS_ANTE_QUEM = 'P1123' # latest possible date of the end of a period
BEGIN_TERMINUS_ANTE_QUEM  = 'P1124' # latest possible date of the begin of a period
END_TERMINUS_POST_QUEM = 'P1125' # earliest possible date of the end of a period
BEGIN_TERMINUS_POST_QUEM = 'P1126' # earliest possible date of the beginning of a period

NOTE = 'P73' # Field for free notes
PRECISION_DATE = 'P467' # FactGrid qualifier for the specific determination of the exactness of a date
PRECISION_BEGIN_DATE = 'P785'   # qualifier to specify a begin date
PRECISION_END_DATE = 'P786'
STRING_PRECISION_BEGIN_DATE = 'P787' # qualifier to specify a begin date; string alternate to P785
STRING_PRECISION_END_DATE = 'P788'

# qualifiers/options
SHORTLY_BEFORE = 'Q255211'
SHORTLY_AFTER = 'Q266009'
LIKELY = 'Q23356'
CIRCA = 'Q10'
OR_FOLLOWING_YEAR = 'Q912616'

def format_datetime(entry: datetime, precision: int):
    ret_val =  f"+{entry.isoformat()}Z/{precision}"

    if entry.year < 1582:
        ret_val +=  JULIAN_ENDING
    
    if precision <= 9:
        ret_val = ret_val.replace(f"{entry.year}-01-01", f"{entry.year}-00-00", 1)

    return ret_val

# only_date=True means there is only one date, not a 'begin date' and an 'end date'
def date_parsing(date_string: str, date_type: DateType):
    qualifier = ""
    entry = None
    precision = PRECISION_CENTURY

    ante_property = (match := re.search(ANTE_GROUP, date_string))
    post_property = (match := re.search(POST_GROUP, date_string))
    assert(not ante_property or not post_property)
    
    match date_type:
        case DateType.ONLY_DATE:
            string_precision_qualifier_clause = NOTE
            exact_precision_qualifier = PRECISION_DATE
            if ante_property:
                return_property = DATE_BEFORE
            elif post_property:
                return_property = DATE_AFTER
            else:
                return_property = DATE
        case DateType.BEGIN_DATE:
            string_precision_qualifier_clause = STRING_PRECISION_BEGIN_DATE
            exact_precision_qualifier = PRECISION_BEGIN_DATE
            if ante_property:
                return_property = BEGIN_TERMINUS_ANTE_QUEM
            elif post_property:
                return_property = BEGIN_TERMINUS_POST_QUEM
            else:
                return_property = BEGIN_DATE
        case DateType.END_DATE:
            string_precision_qualifier_clause = STRING_PRECISION_END_DATE
            exact_precision_qualifier = PRECISION_END_DATE
            if ante_property:
                return_property = END_TERMINUS_ANTE_QUEM
            elif post_property:
                return_property = END_TERMINUS_POST_QUEM
            else:
                return_property = END_DATE    
        case _:
            assert False, "Unexpected DateType!"
        
    string_precision_qualifier_clause += f'\t"{date_string}"'

    if date_string == '?':
        return tuple()
            
    if matches := re.match(r'(\d{1,2})\. ' + JH_GROUP, date_string):
        centuries = int(matches.group(1))
        entry = datetime(100 * (centuries), 1, 1)
    
    elif matches := re.match(r'(\d)\. Hälfte (des )?(\d{1,2})\. ' + JHS_GROUP, date_string):
        half = int(matches.group(1)) - 1
        centuries = int(matches.group(3)) - 1
        year   = centuries * 100 + (half * 50) + QUARTER_OF_A_CENTURY
        entry = datetime(year, 1, 1)
        qualifier = string_precision_qualifier_clause
    
    elif matches := re.match(r'(\w+) Viertel des (\d{1,2})\. ' + JHS_GROUP, date_string):
        number_map = {
            "erstes":  0,
            "zweites": 1,
            "drittes": 2,
            "viertes": 3,
        }
        quarter = matches.group(1)
        centuries = int(matches.group(2))
        year = (centuries - 1) * 100 + (number_map[quarter] * 25) + EIGTH_OF_A_CENTURY
        entry = datetime(year, 1, 1)
        qualifier = string_precision_qualifier_clause

    elif matches := re.match(r'frühes (\d{1,2})\. ' + JH_GROUP, date_string):
        centuries = int(matches.group(1)) - 1
        year = centuries * 100 + TENTH_OF_A_CENTURY
        entry = datetime(year, 1, 1)
        qualifier = string_precision_qualifier_clause

    elif matches := re.match(r'spätes (\d{1,2})\. ' + JH_GROUP, date_string):
        centuries = int(matches.group(1))
        year = centuries * 100 - TENTH_OF_A_CENTURY
        entry = datetime(year, 1, 1)
        qualifier = string_precision_qualifier_clause

    elif matches := re.match(r'(Anfang|Mitte|Ende) (\d{1,2})\. ' + JH_GROUP, date_string):
        number_map = {
            "Anfang":  0,
            "Mitte": 1,
            "Ende": 2,
        }
        third = number_map[matches.group(1)]
        centuries = int(matches.group(2)) - 1
        year = centuries * 100 + (third * 33) + 17
        entry = datetime(year, 1, 1)
        qualifier = string_precision_qualifier_clause

    elif matches := re.match(r'(\d{3,4})er Jahre', date_string):
        entry = datetime(int(matches.group(1)), 1, 1)
        precision = PRECISION_DECADE
    
    elif matches := re.match(r'Wende zum (\d{1,2})\. ' + JH_GROUP, date_string):
        centuries = int(matches.group(1)) - 1
        entry = datetime(centuries * 100 - 10, 1, 1)
        qualifier = string_precision_qualifier_clause

    elif matches := re.match(r'Anfang der (\d{3,4})er Jahre', date_string):
        entry = datetime(int(matches.group(1)), 1, 1)
        qualifier = string_precision_qualifier_clause
        precision = PRECISION_DECADE

    elif matches := re.match(r'\((\d{3,4})\s?\?\) (\d{3,4})', date_string):
        entry = datetime(int(matches.group(2)), 1, 1) # ignoring the year in parantheses
        precision = PRECISION_YEAR
        qualifier = string_precision_qualifier_clause
    
    elif matches := re.match(r'(\d{3,4})/(\d{3,4})', date_string):
        year1 = int(matches.group(1))
        year2 = int(matches.group(2))

        if year2 - year1 == 1:
            # check for consecutive years
            qualifier = exact_precision_qualifier + '\t' + OR_FOLLOWING_YEAR
        entry = datetime(year1, 1, 1)
        precision = PRECISION_YEAR

    # this pattern is pre-compiled above, because it's rather complex and it's much more efficient to compile it just once, instead of on every function call
    elif matches := MOST_COMPLEX_PATTERN.match(date_string):
        if matches.group(1): # if 'wohl' was found
            qualifier = exact_precision_qualifier + '\t' + LIKELY
        if matches.group(5): # if 'etwa' , 'ca.' or 'um' were found
            if len(qualifier) != 0:
                qualifier += '\t'
            qualifier += exact_precision_qualifier + '\t' + CIRCA
                
        if matches.group(3): # if 'kurz' was found -- because of how the regex is defined, this can only happen when combined with 'nach', 'bis', etc.
            if len(qualifier) != 0:
                qualifier += '\t'

            if ante_property: # already checked above whether it's before or after
                qualifier += exact_precision_qualifier + '\t' + SHORTLY_BEFORE
            else: # post_property
                qualifier += exact_precision_qualifier + '\t' + SHORTLY_AFTER

        if matches.group(8): # if a question mark at the end were found
            # TODO is it correct, that on ? the other matches ('ca.' etc.) are ignored, because it's not exact enough?
            qualifier = string_precision_qualifier_clause
        
        entry = datetime(int(matches.group(7)), 1, 1)
        precision = PRECISION_YEAR

    else:
        raise Exception(f"Couldn't parse date '{date_string}'")
        
    return (return_property, format_datetime(entry, precision), qualifier)

# %% [markdown]
# Because there are so many special cases, testing is a must to more clearly show what is expected for each case and make sure no incorrect changes are made.
# TODO why resolution of 7, 8 or 9?

# still to be handled:
    # "Ende 11. Jahrhundert/1. Viertel 12. Jahrhundert": "", TODO what date?
    # "(996)" #TODO mistake or what does this mean?
    # "12. oder 13. Jahrhundert"
    # "(vor 1254) 1256"

begin_date_tests = {
    "1205": (BEGIN_DATE, "+1205-00-00T00:00:00Z/9/J"),
    "1205?": (BEGIN_DATE, "+1205-00-00T00:00:00Z/9/J", STRING_PRECISION_BEGIN_DATE + '\t"1205?"'),
    "12. Jahrhundert": (BEGIN_DATE, "+1200-00-00T00:00:00Z/7/J"),
    "1. Hälfte des 12. Jhs.": (BEGIN_DATE, "+1125-00-00T00:00:00Z/7/J", STRING_PRECISION_BEGIN_DATE + '\t"1. Hälfte des 12. Jhs."'),
    "1. Hälfte des 12. Jahrhunderts": (BEGIN_DATE, "+1125-00-00T00:00:00Z/7/J", STRING_PRECISION_BEGIN_DATE + '\t"1. Hälfte des 12. Jahrhunderts"'),
    "2. Hälfte des 12. Jhs.": (BEGIN_DATE, "+1175-00-00T00:00:00Z/7/J", STRING_PRECISION_BEGIN_DATE + '\t"2. Hälfte des 12. Jhs."'),
    "erstes Viertel des 12. Jhs.": (BEGIN_DATE, "+1113-00-00T00:00:00Z/7/J", STRING_PRECISION_BEGIN_DATE + '\t"erstes Viertel des 12. Jhs."'),
    "zweites Viertel des 12. Jhs.": (BEGIN_DATE, "+1138-00-00T00:00:00Z/7/J", STRING_PRECISION_BEGIN_DATE + '\t"zweites Viertel des 12. Jhs."'),
    "drittes Viertel des 12. Jhs.": (BEGIN_DATE, "+1163-00-00T00:00:00Z/7/J", STRING_PRECISION_BEGIN_DATE + '\t"drittes Viertel des 12. Jhs."'),
    "viertes Viertel des 12. Jhs.": (BEGIN_DATE, "+1188-00-00T00:00:00Z/7/J", STRING_PRECISION_BEGIN_DATE + '\t"viertes Viertel des 12. Jhs."'),
    "frühes 12. Jh.": (BEGIN_DATE, "+1110-00-00T00:00:00Z/7/J", STRING_PRECISION_BEGIN_DATE + '\t"frühes 12. Jh."'),
    "spätes 12. Jh.": (BEGIN_DATE, "+1190-00-00T00:00:00Z/7/J", STRING_PRECISION_BEGIN_DATE + '\t"spätes 12. Jh."'),
    "Anfang 12. Jh.": (BEGIN_DATE, "+1117-00-00T00:00:00Z/7/J", STRING_PRECISION_BEGIN_DATE + '\t"Anfang 12. Jh."'),
    "Anfang 15. Jahrhundert": (BEGIN_DATE, "+1417-00-00T00:00:00Z/7/J", STRING_PRECISION_BEGIN_DATE + '\t"Anfang 15. Jahrhundert"'),
    "Mitte 12. Jh.": (BEGIN_DATE, "+1150-00-00T00:00:00Z/7/J", STRING_PRECISION_BEGIN_DATE + '\t"Mitte 12. Jh."'),
    "Mitte 14. Jahrhundert?": (BEGIN_DATE, "+1350-00-00T00:00:00Z/7/J", STRING_PRECISION_BEGIN_DATE + '\t"Mitte 14. Jahrhundert?"'),
    "Ende 12. Jh.": (BEGIN_DATE, "+1183-00-00T00:00:00Z/7/J", STRING_PRECISION_BEGIN_DATE + '\t"Ende 12. Jh."'),
    "Ende 12. Jahrhundert": (BEGIN_DATE, "+1183-00-00T00:00:00Z/7/J", STRING_PRECISION_BEGIN_DATE + '\t"Ende 12. Jahrhundert"'),
    "bis etwa 1147": (BEGIN_TERMINUS_ANTE_QUEM, '+1147-00-00T00:00:00Z/9/J', PRECISION_BEGIN_DATE + '\t' + CIRCA),
    "etwa 1147": (BEGIN_DATE, '+1147-00-00T00:00:00Z/9/J', PRECISION_BEGIN_DATE + '\t' + CIRCA),
    "ca. 1050": (BEGIN_DATE, "+1050-00-00T00:00:00Z/9/J", PRECISION_BEGIN_DATE + '\t' + CIRCA),
    "um 1050": (BEGIN_DATE, "+1050-00-00T00:00:00Z/9/J", PRECISION_BEGIN_DATE + '\t' + CIRCA),
    "1230er Jahre": (BEGIN_DATE, "+1230-00-00T00:00:00Z/8/J"),
    "Wende zum 12. Jh.": (BEGIN_DATE, '+1090-00-00T00:00:00Z/7/J', STRING_PRECISION_BEGIN_DATE + '\t"Wende zum 12. Jh."'),
    "Anfang der 1480er Jahre": (BEGIN_DATE, '+1480-00-00T00:00:00Z/8/J', STRING_PRECISION_BEGIN_DATE + '\t"Anfang der 1480er Jahre"'),
    "1164/1165": (BEGIN_DATE, '+1164-00-00T00:00:00Z/9/J', PRECISION_BEGIN_DATE + '\t' + OR_FOLLOWING_YEAR),
    "1164/1177": (BEGIN_DATE, '+1164-00-00T00:00:00Z/9/J'),
    "(1014?) 1015": (BEGIN_DATE,"+1015-00-00T00:00:00Z/9/J", STRING_PRECISION_BEGIN_DATE + '\t"(1014?) 1015"'),
    "ab 1534": (BEGIN_TERMINUS_POST_QUEM, '+1534-00-00T00:00:00Z/9/J'),
    "nach 1230": (BEGIN_TERMINUS_POST_QUEM, '+1230-00-00T00:00:00Z/9/J'),
    "kurz nach 1200": (BEGIN_TERMINUS_POST_QUEM, '+1200-00-00T00:00:00Z/9/J', PRECISION_BEGIN_DATE + '\t' + SHORTLY_AFTER),
    "frühestens 1342": (BEGIN_TERMINUS_POST_QUEM, '+1342-00-00T00:00:00Z/9/J'),
    "vor 1230": (BEGIN_TERMINUS_ANTE_QUEM, '+1230-00-00T00:00:00Z/9/J'),
    "wohl vor 1249": (BEGIN_TERMINUS_ANTE_QUEM, '+1249-00-00T00:00:00Z/9/J', PRECISION_BEGIN_DATE + '\t' + LIKELY),
    "kurz vor 1200": (BEGIN_TERMINUS_ANTE_QUEM, '+1200-00-00T00:00:00Z/9/J', PRECISION_BEGIN_DATE + '\t' + SHORTLY_BEFORE), 
    "wohl etwa 1249": (BEGIN_DATE, '+1249-00-00T00:00:00Z/9/J', PRECISION_BEGIN_DATE + '\t' + LIKELY + '\t' + PRECISION_BEGIN_DATE + '\t' + CIRCA),
    "spätestens 1277": (BEGIN_TERMINUS_ANTE_QUEM, '+1277-00-00T00:00:00Z/9/J'),
    #"zwischen 1087 und 1093": (BEGIN_TERMINUS_POST_QUEM,"+1087-00-00T00:00:00Z/9/J", STRING_PRECISION_BEGIN_DATE + '\t"zwischen 1087 und 1093"'), # TODO implement
}

for key, value in begin_date_tests.items():
    retval = date_parsing(key, DateType.BEGIN_DATE)
    if len(retval[2]) == 0:
        retval = (retval[0], retval[1])
    assert retval == value, f"{key}: Returned {retval} instead of {value}"

end_date_tests = {
    "1205?": (END_DATE, "+1205-00-00T00:00:00Z/9/J", STRING_PRECISION_END_DATE + '\t"1205?"'),
    "12. Jahrhundert": (END_DATE, "+1200-00-00T00:00:00Z/7/J"),
    "drittes Viertel des 12. Jhs.": (END_DATE, "+1163-00-00T00:00:00Z/7/J", STRING_PRECISION_END_DATE + '\t"drittes Viertel des 12. Jhs."'),
    "bis etwa 1147": (END_TERMINUS_ANTE_QUEM, '+1147-00-00T00:00:00Z/9/J', PRECISION_END_DATE + '\t' + CIRCA),
    "um 1050": (END_DATE, "+1050-00-00T00:00:00Z/9/J", PRECISION_END_DATE + '\t' + CIRCA),
    "Anfang der 1480er Jahre": (END_DATE, '+1480-00-00T00:00:00Z/8/J', STRING_PRECISION_END_DATE + '\t"Anfang der 1480er Jahre"'),
    "1164/1165": (END_DATE, '+1164-00-00T00:00:00Z/9/J', PRECISION_END_DATE + '\t' + OR_FOLLOWING_YEAR),
    "1164/1177": (END_DATE, '+1164-00-00T00:00:00Z/9/J'),
    "(1014?) 1015": (END_DATE,"+1015-00-00T00:00:00Z/9/J", STRING_PRECISION_END_DATE + '\t"(1014?) 1015"'),
    "ab 1534": (END_TERMINUS_POST_QUEM, '+1534-00-00T00:00:00Z/9/J'),
    "nach 1230": (END_TERMINUS_POST_QUEM, '+1230-00-00T00:00:00Z/9/J'),
    "frühestens 1342": (END_TERMINUS_POST_QUEM, '+1342-00-00T00:00:00Z/9/J'),
    "vor 1230": (END_TERMINUS_ANTE_QUEM, '+1230-00-00T00:00:00Z/9/J'),
    "wohl vor 1249": (END_TERMINUS_ANTE_QUEM, '+1249-00-00T00:00:00Z/9/J', PRECISION_END_DATE + '\t' + LIKELY),
    # "zwischen 1087 und 1093": (BEGIN_TERMINUS_POST_QUEM,"+1087-00-00T00:00:00Z/9/J", STRING_PRECISION_END_DATE + '\t"zwischen 1087 und 1093"'), # TODO implement
}

for key, value in end_date_tests.items():
    retval = date_parsing(key, DateType.END_DATE)
    if len(retval[2]) == 0:
        retval = (retval[0], retval[1])
    assert retval == value, f"{key}: Returned {retval} instead of {value}"

only_date_tests = {
    "1205?": (DATE, "+1205-00-00T00:00:00Z/9/J", NOTE + '\t"1205?"'),
    "12. Jahrhundert": (DATE, "+1200-00-00T00:00:00Z/7/J"),
    "drittes Viertel des 12. Jhs.": (DATE, "+1163-00-00T00:00:00Z/7/J", NOTE + '\t"drittes Viertel des 12. Jhs."'),
    "bis etwa 1147": (DATE_BEFORE, '+1147-00-00T00:00:00Z/9/J', PRECISION_DATE + '\t' + CIRCA),
    "um 1050": (DATE, "+1050-00-00T00:00:00Z/9/J", PRECISION_DATE + '\t' + CIRCA),
    "Anfang der 1480er Jahre": (DATE, '+1480-00-00T00:00:00Z/8/J', NOTE + '\t"Anfang der 1480er Jahre"'),
    "1164/1165": (DATE, '+1164-00-00T00:00:00Z/9/J', PRECISION_DATE + '\t' + OR_FOLLOWING_YEAR),
    "1164/1177": (DATE, '+1164-00-00T00:00:00Z/9/J'),
    "(1014?) 1015": (DATE,"+1015-00-00T00:00:00Z/9/J", NOTE + '\t"(1014?) 1015"'),
    "ab 1534": (DATE_AFTER, '+1534-00-00T00:00:00Z/9/J'),
    "nach 1230": (DATE_AFTER, '+1230-00-00T00:00:00Z/9/J'),
    "frühestens 1342": (DATE_AFTER, '+1342-00-00T00:00:00Z/9/J'),
    "vor 1230": (DATE_BEFORE, '+1230-00-00T00:00:00Z/9/J'),
    "wohl vor 1249": (DATE_BEFORE, '+1249-00-00T00:00:00Z/9/J', PRECISION_DATE + '\t' + LIKELY),
    # "zwischen 1087 und 1093": (DATE,"+1087-00-00T00:00:00Z/9/J", NOTE + '\t"zwischen 1087 und 1093"'), # TODO implement
}

for key, value in only_date_tests.items():
    retval = date_parsing(key, DateType.ONLY_DATE)
    if len(retval[2]) == 0:
        retval = (retval[0], retval[1])
    assert retval == value, f"{key}: Returned {retval} instead of {value}"

# %% [markdown]
#### Reconcile office data with factgrid
#The data that should be in FactGrid:
# %%
final_joined_df.head() 

# %% [markdown]
#And now we check what is already in FactGrid so we only export QuickStatements for new things. This makes it much easier to see afterwards what was changed when e.g. the notebook was run the last time.
# %%
qIDs = final_joined_df.with_columns(query_qID= "(wd:" + pl.col('FactGrid') + ')').get_column("query_qID").unique().sort()

# up to 325 worked, so 300 should be relatively safe, even if a dataset contains only long qIDs
CHUNK_SIZE = 300
entries_processed = 0
factgrid_roles_for_qIDs = pl.DataFrame()

while entries_processed < qIDs.len():
  qID_string = qIDs.slice(offset = entries_processed, length = CHUNK_SIZE).str.join(delimiter = ' ')[0]

  # very helpful resource for building this query: https://en.wikibooks.org/wiki/SPARQL/WIKIDATA_Qualifiers,_References_and_Ranks
  query = (
  """
  SELECT ?person_id ?career_statement ?date ?begin_date ?end_date ?date_after ?date_before ?end_terminus_ante_quem ?begin_terminus_ante_quem ?end_terminus_post_quem ?begin_terminus_post_quem ?precision_date ?precision_begin_date ?precision_end_date ?string_precision_begin_date ?string_precision_end_date ?note ?source WHERE {
    VALUES (?person_id) {
  """
  + qID_string
  +
  """
    }
    ?person_id p:P165 ?statement.
    ?statement ps:P165 ?career_statement.
    
    # different types of dates
    OPTIONAL{?statement pq:P106 ?date.}
    OPTIONAL{?statement pq:P49 ?begin_date.}
    OPTIONAL{?statement pq:P50 ?end_date.}
    OPTIONAL{?statement pq:P41 ?date_after.}
    OPTIONAL{?statement pq:P43 ?date_before.}
    OPTIONAL{?statement pq:P1123 ?end_terminus_ante_quem.}
    OPTIONAL{?statement pq:P1124 ?begin_terminus_ante_quem.}
    OPTIONAL{?statement pq:P1125 ?end_terminus_post_quem.}
    OPTIONAL{?statement pq:P1126 ?begin_terminus_post_quem.}
    
    #qualifiers of dates
    OPTIONAL{?statement pq:467 ?precision_date.}
    OPTIONAL{?statement pq:P785 ?precision_begin_date.}
    OPTIONAL{?statement pq:P786 ?precision_end_date.}
    OPTIONAL{?statement pq:P787 ?string_precision_begin_date.}
    OPTIONAL{?statement pq:P788 ?string_precision_end_date.}
    OPTIONAL{?statement pq:P73 ?note.}
    
    # the source (generally a WIAG-ID)
    OPTIONAL{?statement prov:wasDerivedFrom ?refnode.
            ?refnode pr:P601 ?source.}
  }
  """
  )


  # make request: 
  url = 'https://database.factgrid.de/sparql'
  r = requests.get(url, params={'query': query}, headers={"Accept": "application/json"})

  # should there be an error, explicitly raise it, so it doesn't look as if there was a problem decoding the json response
  r.raise_for_status()

  data = r.json()
  temp_df = pl.json_normalize(data['results']['bindings'])

  # define what columns the dataframe should have
  total_columns_list = ['person_id.value', 'career_statement.value', 'date.value', 'begin_date.value', 'end_date.value', 'date_after.value', 'date_before.value', 'end_terminus_ante_quem.value', 'begin_terminus_ante_quem.value', 'end_terminus_post_quem.value', 'begin_terminus_post_quem.value', 'precision_date.value', 'precision_begin_date.value', 'precision_end_date.value', 'string_precision_begin_date.value', 'string_precision_end_date.value', 'note.value', 'source.value']

  # extract values
  for column in temp_df.columns:
      if column.endswith(".value"):
          temp_df = extract_id(temp_df, column)

          # filter for columns not in the dataframe
          total_columns_list.remove(column)

  # add columns that are missing (because the query did not return any values for that column) - this is necessary for merging the dataframes
  for column in total_columns_list:
      temp_df = temp_df.with_columns(pl.lit(None).cast(pl.String).alias(column))

  # select only relevant columns and rename them at the same time
  temp_df = temp_df.select(qID = 'person_id.value', role = 'career_statement.value', date = 'date.value', begin_date = 'begin_date.value', end_date = 'end_date.value', date_after = 'date_after.value', date_before = 'date_before.value', end_terminus_ante_quem = 'end_terminus_ante_quem.value', begin_terminus_ante_quem = 'begin_terminus_ante_quem.value', end_terminus_post_quem = 'end_terminus_post_quem.value', begin_terminus_post_quem = 'begin_terminus_post_quem.value', precision_date = 'precision_date.value', precision_begin_date = 'precision_begin_date.value', precision_end_date = 'precision_end_date.value', string_precision_begin_date = 'string_precision_begin_date.value', string_precision_end_date = 'string_precision_end_date.value', note = 'note.value', source = 'source.value')

  # sort by qID - relevant for merging
  temp_df = temp_df.sort('qID')

  if factgrid_roles_for_qIDs.is_empty():
    factgrid_roles_for_qIDs = temp_df
  else:
    factgrid_roles_for_qIDs.merge_sorted(other = temp_df, key = 'qID')

  entries_processed += CHUNK_SIZE  

# %%
factgrid_roles_for_qIDs.head(n = 5)
# %% [markdown]
#### Generate quickstatements for offices
#The code below creates the office entries to be uploaded on factgrid.
#If the date parsing fails, the corresponding date string is printed out and along with the entry.
# 
#When the parsing fails, sometimes the date parsing defined above needs to be extended to handle cases that haven't been handled until now and sometimes entries in WIAG need to be corrected.

# %%
filepath = os.path.join(output_path, f'quickstatements_{today_string}.qs')

with open(filepath, 'w') as file:
    for row in final_joined_df.iter_rows(named = True):
        try:
            date_clauses = ()

            if row['date_begin'] != None:
                if row['date_end'] != None:
                    date_clauses = (*date_parsing(row['date_begin'], DateType.BEGIN_DATE), *date_parsing(row['date_end'], DateType.END_DATE))
                else:
                    date_clauses = date_parsing(row['date_begin'], DateType.ONLY_DATE)
            else:
                if row['date_end'] != None:
                    date_clauses = date_parsing(row['date_end'], DateType.ONLY_DATE)
                    
            file.write('\t'.join([
                row['FactGrid'], 
                'P165', 
                row['fg_inst_role_id'],
                'S601', 
                '"' + row['person_id'] + '"',
                *date_clauses,
            ]) + '\n')
        except Exception as e:
            print(traceback.format_exc())
            print(row)
            print('\n')

# %%



