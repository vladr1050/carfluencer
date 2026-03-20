import DOMPurify from 'dompurify';

const ALLOWED_TAGS = ['p', 'br', 'strong', 'em', 'a', 'ul', 'ol', 'li', 'h2', 'h3', 'h4'];
const ALLOWED_ATTR = ['href', 'target', 'rel', 'class'];

export function ContentBlockBody({ body }: { body: string }): JSX.Element {
  const looksLikeHtml = /<[a-z][\s\S]*>/i.test(body);

  if (!looksLikeHtml) {
    return <div className="whitespace-pre-wrap text-sm leading-relaxed text-muted-foreground">{body}</div>;
  }

  const clean = DOMPurify.sanitize(body, { ALLOWED_TAGS, ALLOWED_ATTR });

  return (
    <div
      className="space-y-2 text-sm leading-relaxed text-muted-foreground [&_a]:font-medium [&_a]:text-brand-magenta [&_a]:underline [&_h2]:text-base [&_h3]:text-sm [&_ul]:list-disc [&_ul]:pl-5"
      dangerouslySetInnerHTML={{ __html: clean }}
    />
  );
}
