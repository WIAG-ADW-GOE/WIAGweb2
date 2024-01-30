function val_sql(val::Real)
    return string(val)
end

function val_sql(val::AbstractString)
    val = replace(val, "\"" => "'")
    return "\"" * val * "\""
end

function val_sql(val::Missing)
    return "NULL" # not "'NULL'"
end

const wiag_date_time_format = Dates.dateformat"yyyy-mm-dd HH:MM"
function val_sql(val::DateTime)
    return "'" * Dates.format(val, wiag_date_time_format) * "'"
end

const wiag_date_format = Dates.dateformat"yyyy-mm-dd"
function val_sql(val::Date)
    return "'" * Dates.format(val, wiag_date_format) * "'"
end

function val_sql(val::Any)
    return string(val)
end

"""
    insert_sql(file_name, table_name, df::AbstractDataFrame; msg = 2000)

write content of `df` as an SQL INSERT statement for `table_name`
"""
function insert_sql(file_name, table_name, df::AbstractDataFrame; msg = 2000)
    file = open(file_name, "w")
    println(file, "LOCK TABLES `" * table_name * "` WRITE;")

    col_str = join(string.(names(df)), ", ")
    println(file, "INSERT INTO " * table_name * " (" * col_str * ") VALUES")
    size_df_1 = size(df, 1)
    i = 0
    for row in eachrow(df)
        val_line = "(" * join((val_sql(val) for val in row), ", ") * ")"
        print(file, val_line)
        i += 1
        if (i < size_df_1)
            println(file, ",")
        else
            println(file, ";")
        end
        if i % msg == 0
            @info "row " i
        end
    end

    println(file, "UNLOCK TABLES;")
    close(file)
    return i
end

"""
    update_sql(file_name, table_name, df::AbstractDataFrame; on = :id, msg = 2000)

update table_name with values in df join via column on
"""
function update_sql(file_name, table_name, df::AbstractDataFrame; on = :id, msg = 2000)
    file = open(file_name, "w")
    println(file, "LOCK TABLES `" * table_name * "` WRITE;")

    c_col = filter(!isequal(on), Symbol.(names(df)))
    size_df_1 = size(df, 1)
    i = 0
    for row in eachrow(df)
        c_assignment = String[];
        for c in c_col
            a = string(c) * " = " * val_sql(row[c])
            push!(c_assignment, a)
        end
        c_token = ["UPDATE",
                   table_name,
                   "SET",
                   join(c_assignment, ", "),
                   "WHERE",
                   string(on) * " = " * val_sql(row[on])]
        println(file, join(c_token, " ") * ";")

        i += 1
        if i % msg == 0
            @info "row " i
        end
    end

    println(file, "UNLOCK TABLES;")
    close(file)
    return i
end

"""
    make_id_public(mask, counter)
"""
function make_id_public(mask, counter)
    rgx = r"#+"
    m = match(rgx, mask)
    number_str = lpad(counter, length(m.match), "0")
    id_public = replace(mask, rgx => number_str, count = 1)
    id_public = replace(id_public, rgx => "001")
    return id_public
end

struct Date_Regex
    rgx::Regex
    part::Int
    sort::Int
end

# parse time data
const rgpcentury = "([1-9][0-9]?)\\. (Jahrh|Jh)"
const rgpyear = "([1-9][0-9][0-9]+)"
const rgpyearfc = "([1-9][0-9]+)"

# turn of the century
const rgxtcentury = Regex("([1-9][0-9]?)\\.(/| oder )" * rgpcentury, "i")

# quarter
const rgx1qcentury = Regex("(1\\.|erstes) Viertel +(des )?" * rgpcentury, "i")
const rgx2qcentury = Regex("(2\\.|zweites) Viertel +(des )?" * rgpcentury, "i")
const rgx3qcentury = Regex("(3\\.|drittes) Viertel +(des )?" * rgpcentury, "i")
const rgx4qcentury = Regex("(4\\.|viertes) Viertel +(des )?" * rgpcentury, "i")

# begin, middle end
const rgx1tcentury = Regex("Anfang (des )?" * rgpcentury, "i")
const rgx1atcentury = Regex("Beginn (des )?" * rgpcentury, "i")
const rgx2tcentury = Regex("Mitte (des )?" * rgpcentury, "i")
const rgx3tcentury = Regex("Ende (des )?" * rgpcentury, "i")

# third
const rgx1trdcentury = Regex("(1\\.|erstes) Drittel +(des )?" * rgpcentury, "i")
const rgx2trdcentury = Regex("(2\\.|zweites) Drittel +(des )?" * rgpcentury, "i")
const rgx3trdcentury = Regex("(3\\.|drittes) Drittel +(des )?" * rgpcentury, "i")


# half
const rgx1hcentury = Regex("(1\\.|erste) Hälfte +(des )?" * rgpcentury, "i")
const rgx2hcentury = Regex("(2\\.|zweite) Hälfte +(des )?" * rgpcentury, "i")

# between
const rgxbetween = Regex("zwischen " * rgpyear * " und " * rgpyear)

# early, late
const rgxearlycentury = Regex("frühes " * rgpcentury, "i")
const rgxlatecentury = Regex("spätes " * rgpcentury, "i")

# around, ...
const rgpmonth = "(Januar|Februar|März|April|Mai|Juni|Juli|August|September|Oktober|November|Dezember|Jan\\.|Feb\\.|Mrz\\.|Apr\\.|Jun\\.|Jul\\.|Aug\\.|Sep\\.|Okt\\.|Nov\\.|Dez\\.)"
const rgxbefore = Regex("(vor|bis|spätestens|spät\\.|v\\.)( [1-9][0-9]?\\.)? " * rgpmonth * "? ?" * rgpyear, "i")
# add 'circa'
const rgxca = Regex("(circa|ca\\.|wahrscheinlich|wohl|etwa|evtl\\.) " * rgpyear, "i")
const rgxaround = Regex("(um) " * rgpyear, "i")
const rgxafter = Regex("(nach|frühestens|seit|ab) " * rgpyear, "i")

const rgxcentury = Regex("^ *" * rgpcentury)
const rgxyear = Regex("^( *|erwählt |belegt )" * rgpyear)
const rgxyearfc = Regex("^( *|erwählt |belegt )" * rgpyearfc)
const stripchars = ['†', ' ']

"""
    parsemaybe(s, Symbol::dir)

Parse `s` for an earliest or latest date. `dir` is `:upper` or `:lower`
"""
function parsemaybe(s, dir::Symbol)::Union{Missing, Int}
    if !(dir in [:lower, :upper])
        error("parameter dir must be :lower or :upper got ", dir)
    end

    year = missing
    if ismissing(s)
        return year
    end

    # handle special cases
    s = strip(s, stripchars)

    if s == "?" || s == "" return year end

    # turn of the century
    rgm = match(rgxtcentury, s)
    if !isnothing(rgm) && !isnothing(rgm[1]) && !isnothing(rgm[3])
        if dir == :lower
            century = parse(Int, rgm[1])
            return year = (century - 1) * 100 + 1
        elseif dir == :upper
            century = parse(Int, rgm[3])
            return year = century * 100 - 1
        end
    end

    # quarter
    rgxq = [rgx1qcentury, rgx2qcentury, rgx3qcentury, rgx4qcentury]
    for (q, rgx) in enumerate(rgxq)
        rgm = match(rgx, s)
        if !isnothing(rgm) && !isnothing(rgm[3])
            century = parse(Int, rgm[3])
            if dir == :lower
                year = (century - 1) * 100 + (q - 1) * 25 + 1
                return year
            elseif dir == :upper
                year = (century - 1) * 100 + q * 25
                return year
            end
        end
    end

    # begin, middle, end
    rgxq = [rgx1tcentury, rgx1atcentury, rgx2tcentury, rgx3tcentury]
    for (q, rgx) in enumerate(rgxq)
        rgm = match(rgx, s)
        if !isnothing(rgm) && !isnothing(rgm[2])
            century = parse(Int, rgm[2])
            if dir == :lower
                year = (century - 1) * 100 + (q - 1) * 33 + 1
                return year
            elseif dir == :upper
                year = (century - 1) * 100 + q * 33 + (q == 3 ? 1 : 0)
                return year
            end
        end
    end

    # half
    rgxq = [rgx1hcentury, rgx2hcentury]
    for (q, rgx) in enumerate(rgxq)
        rgm = match(rgx, s)
        if !isnothing(rgm) && !isnothing(rgm[3])
            century = parse(Int, rgm[3])
            if dir == :lower
                year = (century - 1) * 100 + (q - 1) * 50 + 1
                return year
            elseif dir == :upper
                year = (century - 1) * 100 + q * 50
                return year
            end
        end
    end

    # between
    rgm = match(rgxbetween, s)
    if !isnothing(rgm) && !isnothing(rgm[1]) && !isnothing(rgm[2])
        if dir == :lower
            year = parse(Int, rgm[1])
            return year
        elseif dir == :upper
            year = parse(Int, rgm[2])
            return year
        end
    end

    # early, late
    rgm = match(rgxearlycentury, s)
    if !isnothing(rgm) && !isnothing(rgm[1])
        century = parse(Int, rgm[1])
        if dir == :lower
            year = (century - 1) * 100 + 1
            return year
        elseif dir == :upper
            year = (century - 1) * 100 + 20
            return year
        end
    end

    rgm = match(rgxlatecentury, s)
    if !isnothing(rgm) && !isnothing(rgm[1])
        century = parse(Int, rgm[1])
        if dir == :lower
            year = century * 100 - 19
            return year
        elseif dir == :upper
            year = century * 100
            return year
        end
    end


    # before, around, after
    rgm = match(rgxbefore, s)
    if !isnothing(rgm)
        year = parse(Int, rgm[4])
        if dir == :lower
            year -= 50
        end
        return year
    end

    rgm = match(rgxafter, s)
    if !isnothing(rgm)
        year = parse(Int, rgm[2])
        if dir == :upper
            year += 50
        end
        return year
    end

    rgm = match(rgxaround, s)
    if !isnothing(rgm)
        year = parse(Int, rgm[2])
        if dir == :lower
            year -= 5
        elseif dir == :upper
            year += 5
        end
        return year
    end

    rgm = match(rgxca, s)
    if !isnothing(rgm)
        year = parse(Int, rgm[2])
        if dir == :lower
            year -= 5
        elseif dir == :upper
            year += 5
        end
        return year
    end

    # century
    rgm = match(rgxcentury, s)
    if !isnothing(rgm) && !isnothing(rgm[1])
        century = parse(Int, rgm[1])
        if dir == :lower
            year = (century - 1) * 100 + 1
        elseif dir == :upper
            year = century * 100
        end
        return year
    end

    # plain year
    rgm = match(rgxyear, s)
    if !isnothing(rgm) && !isnothing(rgm[2])
        year = parse(Int, rgm[2])
        return year
    end


    # handle other special cases
    if strip(s) == "?" return year end

    ssb = replace(s, r"\((.+)\)" => s"\1")
    if ssb != s
        return parsemaybe(ssb, dir)
    end

    # try to find a year
    rgxyearpx = r"([1-9][0-9][0-9][0-9]?)"
    rgm = match(rgxyearpx, s)
    if !isnothing(rgm) && !isnothing(rgm[1])
        @warn "Could only find year in " s
        year = parse(Int, rgm[1])
        return year
    end

    @warn "Could not parse " s
    return year

end

"""
    c_rgx_sort_cty

regular expressions for dates (century)

structure: regular expression, index of the relevant part, sort key
"""
c_rgx_sort_cty = [
    Date_Regex(rgxtcentury, 1, 850),
    Date_Regex(rgx1qcentury, 3, 530),
    Date_Regex(rgx2qcentury, 3, 560),
    Date_Regex(rgx3qcentury, 3, 580),
    Date_Regex(rgx4qcentury, 3, 595),
    Date_Regex(rgx1tcentury, 2, 500),
    Date_Regex(rgx1atcentury, 2, 500),
    Date_Regex(rgx2tcentury, 2, 570),
    Date_Regex(rgx3tcentury, 2, 594),
    Date_Regex(rgx1trdcentury, 3, 500),
    Date_Regex(rgx2trdcentury, 3, 570),
    Date_Regex(rgx3trdcentury, 3, 594),
    Date_Regex(rgx1hcentury, 3, 550),
    Date_Regex(rgx2hcentury, 3, 590),
    Date_Regex(Regex("(wohl im )" * rgpcentury), 2, 810),
    Date_Regex(rgxearlycentury, 1, 555),
    Date_Regex(rgxlatecentury, 1, 593),
    Date_Regex(rgxcentury, 1, 800)
]

"""
    c_rgx_sort

regular expressions for dates

structure: regular expression, index of the relevant part, sort key
"""
c_rgx_sort = [
    Date_Regex(Regex("(kurz vor|bis kurz vor)([1-9][0-9]?\\.)? " * rgpmonth * "? ?" * rgpyear, "i"), 4, 105),
    Date_Regex(rgxbefore, 4, 100),
    Date_Regex(rgxaround, 2, 210),
    Date_Regex(rgxca, 2, 200),
    Date_Regex(Regex("(erstmals erwähnt) " * rgpyear, "i"), 2, 110),
    Date_Regex(Regex("(kurz nach|bald nach) " * rgpyear, "i"), 2, 303),
    Date_Regex(Regex("(Anfang der )" * rgpyear * "er Jahre"), 2, 305),
    Date_Regex(rgxafter, 2, 309),
    Date_Regex(Regex(rgpyear * "er Jahre"), 1, 310),
    Date_Regex(rgxyear, 2, 150),
    Date_Regex(rgxyearfc, 2, 150)
]

"""
    date_sort_key(s)

return a value for a year and a sort key

# Examples
"kurz vor 1200" -> 1200105183
"""
function date_sort_key(s)
    year = 9000
    sort = 900
    day = 900
    # day_middle = 183

    # version for day specific dates
    # make_key(year, sort, day) = (year * 1000 + sort) * 1000 + day
    make_key(year, sort) = year * 1000 + sort
    key_not_found = make_key(9000, 900)

    if ismissing(s)
        return make_key(year, sort)
    end

    s = strip(s, stripchars)
    if s in ("", "?", "unbekannt")
        return make_key(year, sort)
    end

    rgm = match(rgxbetween, s)
    if !isnothing(rgm) && !isnothing(rgm[1]) && !isnothing(rgm[2])
        year_lower = parse(Int, rgm[1])
        year_upper = parse(Int, rgm[2])
        year = div(year_lower + year_upper, 2)
        if year > 3000
            @warn "year out of range in " s
            return key_not_found
        end
        return  make_key(year, 150)
    end

    for d in c_rgx_sort_cty
        rgm = match(d.rgx, s)
        if !isnothing(rgm) && !isnothing(rgm[d.part])
            century = parse(Int, rgm[d.part])
            year = (century - 1) * 100;
            sort = d.sort
            if year > 3000
                @warn "year out of range in " s
                return key_not_found
            end
            return make_key(year, sort)
        end

    end

    for d in c_rgx_sort
        rgm = match(d.rgx, s)
        if !isnothing(rgm) && !isnothing(rgm[d.part])
            year = parse(Int, rgm[d.part])
            sort = d.sort
            if year > 3000
                @warn "year out of range in " s
                return key_not_found
            end
            return make_key(year, sort)
        end

    end

    @warn "could not parse " s
    return key_not_found
end
