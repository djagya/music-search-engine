import axios, { AxiosResponse } from 'axios';
import { ErrorResponse, RelatedSuggestion, Suggestion, TypingResponse } from './types';

const relatedExample: RelatedSuggestion[] = [
  { id: 'rel1', value: 'Related 1' },
  { id: 'rel2', value: 'Related 2' },
  { id: 'rel3', value: 'Related 3' },
];

export function fetchSuggestions(field: string, value: string): Promise<TypingResponse | ErrorResponse> {
  return axios
    .get('/typing', { params: { field, query: value.trim(), meta: 0 } })
    .then((res: AxiosResponse<TypingResponse>) => {
      return res.data;
    })
    .catch(err => {
      console.log(err);
      return <ErrorResponse>{ error: JSON.stringify(err) };
    });
}

interface RequestData {
  [k: string]: string;
}

export function fetchRelatedSuggestions(
  field: string,
  selectedFields: { [k: string]: Suggestion | null },
): Promise<RelatedSuggestion[]> {
  const data: RequestData = Object.keys(selectedFields).reduce((res: RequestData, k: string) => {
    res[k] = selectedFields[k]!.id;
    return res;
  }, {});

  return axios
    .get('/related', { params: { f: field, q: data } })
    .then(res => {
      console.log(res);
      return res.data || [];
    })
    .catch(err => {
      console.log(err);
      return relatedExample;
    });
}
