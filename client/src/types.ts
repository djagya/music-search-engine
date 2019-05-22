export interface SearchResponse {
  maxScore: number;
  total: { value: number; relation: string };
  hits: Suggestion[];
  aggregations: Suggestion[];
}

// todo: what other information is needed? relations, number of results, score?...
export interface Suggestion {
  id: string; // ES id or random id generated on server for aggregated suggestions
  value: string;
  score: number;
  count: number;

  _id?: string; // internal DB id
  _index?: string;
  values?: { [field: string]: string };
}


export interface RelatedResponse {
  fields: { [field: string]: SearchResponse };
}

export interface SelectedFields {
  [name: string]: Suggestion | null;
}


export interface RelatedSuggestion {
  id: string;
  value: string;
  // todo: relation or what? or maybe index/field name?
}

export interface ErrorResponse {
  error: string;
}
