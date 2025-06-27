# %% [markdown]
# # 7. fg_to_pr.ipynb
# ## Add Factgrid IDs in Personen Register

# %% [markdown]
# This notebook helps ensure that the FactGrid IDs stored in a local "Personen Register" are correct and up to date. It starts by getting a list of people and their FactGrid IDs from the FactGrid website. It then compares this list to the records stored in the local Personen Register database, which comes from a downloaded CSV file.
# 
# By checking which IDs match and which do not, the notebook identifies differences, such as records in FactGrid that aren’t in the Personen Register, or people in the Personen Register whose FactGrid ID is incorrect or outdated. After finding these differences, it creates an easy-to-use set of SQL commands. These commands can be run directly on the Personen Register database to fix the FactGrid IDs and ensure both sources of information match.
# 
# In other words, this process helps keep the local database of individuals aligned with the official FactGrid information, making sure that anyone looking up a person’s record sees the correct data.

# %%
import requests
import csv
import os
import pandas as pd
import json
import re
import time
from datetime import datetime, timedelta
import math
import traceback

# %%
input_path = r"C:\Users\khan32\Documents\WIAGweb2\notebooks\sync_notebooks\input_files"
output_path = r"C:\Users\khan32\Documents\WIAGweb2\notebooks\sync_notebooks\output_files"

# %%
url = 'https://database.factgrid.de/sparql'
query = (
"""SELECT ?item ?prid WHERE {
  ?item wdt:P472 ?prid.
}""")
# SERVICE wikibase:label { bd:serviceParam wikibase:language "[AUTO_LANGUAGE],en". }

r = requests.get(url, params={'query': query}, headers={"Accept": "application/json"})
data = r.json()
factgrid_df = pd.json_normalize(data['results']['bindings'])

len(factgrid_df)

# %%
factgrid_df

# %%
# extract out q id
def extract_qid(df, column):
    df[column] = df[column].map(lambda x: x.strip('https://database.factgrid.de/entity/'))
 
#factgrid_df['item.value'] = factgrid_df['item.value'].map(lambda x: x.strip('https://database.factgrid.de/entity/'))

# drop irrelevant columns
def drop_type_columns(df):
    df.drop(columns=[column for column in df.columns if column.endswith('type')], inplace=True)
    df.drop(columns=[column for column in df.columns if column.endswith('xml:lang')], inplace=True)

# %%
drop_type_columns(factgrid_df)
extract_qid(factgrid_df, 'item.value')
factgrid_df.columns = ['FactGrid_ID', 'pd_id']
factgrid_df

# %% [markdown]
# 
# Run this query on personen register and export the results to a csv with the default export settings on phpmyadmin. Please refer to the instructions at [Run_SQL_Query_and_Export_CSV.md](https://github.com/WIAG-ADW-GOE/WIAGweb2/blob/main/notebooks/sync_notebooks/docs/Run_SQL_Query_and_Export_CSV.md) for a detailed explanation of running the sql command below. 
# 
# ```sql
# SELECT persons.factgrid, persons.id, gsn.nummer 
# FROM items INNER JOIN persons ON persons.item_id = items.id AND persons.deleted=0 AND items.deleted=0 AND items.status = "online" 
# INNER JOIN gsn ON gsn.item_id = items.id AND gsn.deleted=0 
# ```

# %%
filename = 'persons_2024-07-03.csv'

# %%
pr_df = pd.read_csv(os.path.join(input_path, filename), names=["fg_id", "id", "pd_id"])
pr_df

# %%
joined_df = factgrid_df.merge(pr_df, how='outer', on='pd_id', suffixes=('_wiag', '_pd'), indicator=True)
joined_df

# %% [markdown]
# The output of the cell below should be empty. The cell after it will produce links to the entries on personendatenbank if this is not the case

# %%
joined_df[joined_df['_merge'] == 'left_only']

# %%
for _, row in joined_df[joined_df['_merge'] == 'left_only'].iterrows():
    print('https://personendatenbank.germania-sacra.de/index/gsn/' + row['pd_id'])

# %%
join_df = joined_df[joined_df['_merge'] == 'both']
join_df

# %%
unequal_df = join_df[join_df['FactGrid_ID'] != join_df['fg_id']]
unequal_df

# %% [markdown]
# generate links to check for possible duplicates on factgrid

# %%
linkify = lambda x : 'https://database.factgrid.de/wiki/Item:' + x 
for _, row in unequal_df.iterrows():
    if not pd.isna(row['fg_id']):
        print(linkify (row['FactGrid_ID']), linkify (row['fg_id']))

# %%
today_string = datetime.now().strftime('%Y-%m-%d')

# %%
query = "LOCK TABLES persons WRITE;\n"
for _, row in unequal_df.iterrows():
    query += f"""
    UPDATE persons
    SET factgrid = '{row['FactGrid_ID']}'
    WHERE id = {row['id']}; -- id: {row['pd_id']}
"""
query += "\nUNLOCK TABLES;"
with open(os.path.join(output_path, f'update_pr_fg_ids_{today_string}.sql'), 'w') as file:
    file.write(query)

# %% [markdown]
# Please run this generated SQL on the personen register database.

# %%



