// todo: what other information is needed? relations, number of results, score?...
export interface Suggestion {
    id: string;
    value: string;
}

export interface RelatedSuggestion {
    id: string;
    value: string;
    // todo: relation or what? or maybe index/field name?
}
